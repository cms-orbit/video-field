<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;
use CmsOrbit\VideoField\Entities\Video\VideoEncodingLog;
use CmsOrbit\VideoField\Traits\VideoJobTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Exception;

class VideoEncodeJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, VideoJobTrait;

    public $timeout = 3600; // 1 hour
    public $tries = 3;

    protected Video $video;
    protected ?array $modelProfiles;
    protected bool $force;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, ?array $modelProfiles = null, bool $force = false)
    {
        $this->video = $video;
        $this->modelProfiles = $modelProfiles;
        $this->force = $force;
    }

    /**
     * Get the video instance.
     */
    public function getVideo(): Video
    {
        return $this->video;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $videoId = $this->video->getAttribute('id');
            $this->logJobStart('video encoding', $videoId);

            // Update video status (use valid enum value)
            $this->video->update(['status' => 'processing']);

            // Perform encoding
            $success = $this->encodeVideo();

            if ($success) {
                $this->video->update(['status' => 'completed']);
                $this->logJobCompletion('video encoding', $videoId);
            } else {
                $this->video->update(['status' => 'failed']);
                $this->logJobError('video encoding', $videoId, 'Encoding process failed');
            }

        } catch (Exception $e) {
            $this->video->update(['status' => 'failed']);
            $this->logJobError('video encoding', $this->video->getAttribute('id'), $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->video->update(['status' => 'failed']);

        Log::error("Video encoding job failed for video: {$this->video->getAttribute('id')}", [
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Encode video with all suitable profiles.
     */
    private function encodeVideo(): bool
    {
        // Check if FFmpeg is available
        if (!$this->checkFFmpeg()) {
            $this->logJobError('video encoding', $this->video->getAttribute('id'), 'FFmpeg not found');
            return false;
        }

        // Find original file
        try {
            $originalPath = $this->video->getVideoPath();
        } catch (Exception $e) {
            $this->logJobError('video encoding', $this->video->getAttribute('id'), 'Failed to get video path: ' . $e->getMessage());
            return false;
        }

        if (!$originalPath || !file_exists($originalPath)) {
            $this->logJobError('video encoding', $this->video->getAttribute('id'), 'Original video file not found at: ' . $originalPath);
            return false;
        }

        // Get video metadata
        $metadata = $this->getVideoMetadata($originalPath);
        if (!$metadata) {
            $this->logJobError('video encoding', $this->video->getAttribute('id'), 'Failed to extract video metadata');
            return false;
        }

        // Update video with metadata
        $this->video->update([
            'duration' => $metadata['duration'] ?? null,
            'original_width' => $metadata['width'] ?? null,
            'original_height' => $metadata['height'] ?? null,
            'original_framerate' => $metadata['framerate'] ?? null,
            'original_bitrate' => $metadata['bitrate'] ?? null,
        ]);

        // Get profiles to encode - use model profiles if available, otherwise use config
        $allProfiles = $this->modelProfiles ?? config('orbit-video.default_profiles', []);
        $suitableProfiles = $this->selectSuitableProfiles($metadata, $allProfiles);

        if (empty($suitableProfiles)) {
            Log::warning('No suitable profiles found for encoding for video: ' . $this->video->getAttribute('id'));
            return true; // Not an error, just no encoding needed
        }

        $successCount = 0;
        foreach ($suitableProfiles as $profileName => $profileConfig) {
            if ($this->encodeProfile($profileName, $profileConfig, $originalPath, $metadata)) {
                $successCount++;
            }
        }

        $totalProfiles = count($suitableProfiles);
        $this->logJobCompletion('video encoding', $this->video->getAttribute('id'), [
            'successful_profiles' => $successCount,
            'total_profiles' => $totalProfiles
        ]);

        return $successCount > 0; // Success if at least one profile was encoded
    }

    /**
     * Encode a single profile.
     */
    private function encodeProfile(string $profileName, array $profileConfig, string $originalPath, array $metadata): bool
    {
        try {
            // Check if profile already exists and skip if not forcing
            $existingProfile = VideoProfile::where('video_id', $this->video->getAttribute('id'))
                ->where('profile', $profileName)
                ->first();

            if ($existingProfile && !$this->force) {
                $disk = config('orbit-video.storage.disk');
                if (Storage::disk($disk)->exists($existingProfile->generateProfilePath())) {
                    Log::info("Profile {$profileName} already exists, skipping");
                    return true;
                }
            }

            // Create or update video profile record
            $videoProfile = VideoProfile::updateOrCreate(
                [
                    'video_id' => $this->video->getAttribute('id'),
                    'field' => 'default',
                    'profile' => $profileName,
                ],
                [
                    'status' => 'processing',
                ]
            );

            // Create encoding log
            $encodingLog = $videoProfile->encodingLogs()->create([
                'status' => 'started',
                'message' => 'Encoding started',
                'progress' => 0,
                'ffmpeg_command' => 'Building command...',
            ]);

            // Encode both HLS and DASH formats
            $hlsSuccess = $this->encodeHlsProfile($videoProfile, $originalPath, $profileConfig, $encodingLog);
            $dashSuccess = $this->encodeDashProfile($videoProfile, $originalPath, $profileConfig, $encodingLog);

            if ($hlsSuccess || $dashSuccess) {
                // Update profile and log
                $videoProfile->update([
                    'status' => 'completed',
                    'encoded' => true,
                    'width' => $profileConfig['width'] ?? null,
                    'height' => $profileConfig['height'] ?? null,
                    'framerate' => $profileConfig['framerate'] ?? null,
                    'bitrate' => $profileConfig['bitrate'] ?? null,
                ]);

                $encodingLog->update([
                    'status' => 'completed',
                    'message' => 'Encoding completed',
                    'progress' => 100,
                ]);

                Log::info("Profile {$profileName} encoded successfully");
                return true;
            } else {
                $videoProfile->update(['status' => 'failed']);
                $encodingLog->update([
                    'status' => 'error',
                    'error_output' => 'Both HLS and DASH encoding failed',
                ]);

                Log::error("Profile {$profileName} encoding failed");
                return false;
            }

        } catch (Exception $e) {
            if (isset($videoProfile)) {
                $videoProfile->update(['status' => 'failed']);
            }
            if (isset($encodingLog)) {
                $encodingLog->update([
                    'status' => 'error',
                    'error_output' => $e->getMessage(),
                ]);
            }

            Log::error("Exception encoding profile {$profileName}: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Select suitable profiles based on video metadata.
     */
    private function selectSuitableProfiles(array $metadata, array $allProfiles): array
    {
        $suitable = [];
        $originalWidth = $metadata['width'] ?? 0;
        $originalHeight = $metadata['height'] ?? 0;
        $originalFramerate = $metadata['framerate'] ?? 30;

        // If using model profiles, process them as simple profile names
        if ($this->modelProfiles && is_array($this->modelProfiles)) {
            $configProfiles = config('orbit-video.default_profiles', []);
            foreach ($this->modelProfiles as $profileName) {
                if (isset($configProfiles[$profileName])) {
                    $config = $configProfiles[$profileName];
                    $profileWidth = $config['width'] ?? 0;
                    $profileHeight = $config['height'] ?? 0;

                    // Skip if profile resolution is higher than original
                    if ($profileWidth <= $originalWidth && $profileHeight <= $originalHeight) {
                        $suitable[$profileName] = $config;
                    }
                }
            }
            return $suitable;
        }

        foreach ($allProfiles as $profileName => $config) {

            $profileWidth = $config['width'] ?? 0;
            $profileHeight = $config['height'] ?? 0;
            $profileFramerate = $config['framerate'] ?? 30;

            // Skip if profile resolution is higher than original
            if ($profileWidth > $originalWidth || $profileHeight > $originalHeight) {
                continue;
            }

            // Skip if profile framerate is significantly higher than original (allow 5fps tolerance)
            if ($profileFramerate > ($originalFramerate + 5)) {
                continue;
            }

            $suitable[$profileName] = $config;
        }

        return $suitable;
    }

    /**
     * Encode HLS profile (TS segments + M3U8 playlist).
     */
    private function encodeHlsProfile(VideoProfile $videoProfile, string $originalPath, array $config, $encodingLog): bool
    {
        try {
            $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');
            $disk = config('orbit-video.storage.disk');
            
            // HLS output directory
            $hlsDir = $videoProfile->generateHlsDirectory();
            $fullHlsDir = Storage::disk($disk)->path($hlsDir);
            $this->ensureDirectoryExists($fullHlsDir);
            
            // HLS segment duration (in seconds)
            $segmentDuration = 10;
            
            $command = [
                $ffmpegPath,
                '-i', $originalPath,
                '-c:v', $config['codec'] ?? 'libx264',
                '-b:v', $config['bitrate'],
                '-r', (string)$config['framerate'],
                '-profile:v', $config['profile'] ?? 'main',
                '-level', $config['level'] ?? '4.0',
                '-s', "{$config['width']}x{$config['height']}",
                '-c:a', 'aac',
                '-b:a', '128k',
                '-f', 'hls',
                '-hls_time', (string)$segmentDuration,
                '-hls_list_size', '0',
                '-hls_segment_filename', $fullHlsDir . '/segment_%03d.ts',
                '-y', // Overwrite output file
                $fullHlsDir . '/playlist.m3u8'
            ];

            // Update log with actual command
            $encodingLog->update(['ffmpeg_command' => implode(' ', $command)]);

            Log::info("Starting HLS encoding for profile: {$videoProfile->getAttribute('profile')}");

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Update profile with HLS path
                $videoProfile->update(['hls_path' => $hlsDir . '/playlist.m3u8']);
                Log::info("HLS profile {$videoProfile->getAttribute('profile')} encoded successfully");
                return true;
            } else {
                $errorOutput = $process->getErrorOutput();
                Log::error("HLS profile {$videoProfile->getAttribute('profile')} encoding failed: {$errorOutput}");
                return false;
            }

        } catch (Exception $e) {
            Log::error("Exception encoding HLS profile {$videoProfile->getAttribute('profile')}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Encode DASH profile (MP4 segments + MPD manifest).
     */
    private function encodeDashProfile(VideoProfile $videoProfile, string $originalPath, array $config, $encodingLog): bool
    {
        try {
            $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');
            $disk = config('orbit-video.storage.disk');
            
            // DASH output directory
            $dashDir = $videoProfile->generateDashDirectory();
            $fullDashDir = Storage::disk($disk)->path($dashDir);
            $this->ensureDirectoryExists($fullDashDir);
            
            // DASH segment duration (in seconds)
            $segmentDuration = 10;
            
            $command = [
                $ffmpegPath,
                '-i', $originalPath,
                '-c:v', $config['codec'] ?? 'libx264',
                '-b:v', $config['bitrate'],
                '-r', (string)$config['framerate'],
                '-profile:v', $config['profile'] ?? 'main',
                '-level', $config['level'] ?? '4.0',
                '-s', "{$config['width']}x{$config['height']}",
                '-c:a', 'aac',
                '-b:a', '128k',
                '-f', 'dash',
                '-seg_duration', (string)$segmentDuration,
                '-use_template', '1',
                '-use_timeline', '1',
                '-y', // Overwrite output file
                $fullDashDir . '/manifest.mpd'
            ];

            // Update log with actual command
            $encodingLog->update(['ffmpeg_command' => implode(' ', $command)]);

            Log::info("Starting DASH encoding for profile: {$videoProfile->getAttribute('profile')}");

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Update profile with DASH path
                $videoProfile->update(['dash_path' => $dashDir . '/manifest.mpd']);
                Log::info("DASH profile {$videoProfile->getAttribute('profile')} encoded successfully");
                return true;
            } else {
                $errorOutput = $process->getErrorOutput();
                Log::error("DASH profile {$videoProfile->getAttribute('profile')} encoding failed: {$errorOutput}");
                return false;
            }

        } catch (Exception $e) {
            Log::error("Exception encoding DASH profile {$videoProfile->getAttribute('profile')}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build FFmpeg command for encoding.
     */
    private function buildFFmpegCommand(string $inputPath, string $outputPath, array $config): array
    {
        $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');
        $disk = config('orbit-video.storage.disk');
        $fullOutputPath = Storage::disk($disk)->path($outputPath);

        // Ensure output directory exists
        $outputDir = dirname($fullOutputPath);
        $this->ensureDirectoryExists($outputDir);

        return [
            $ffmpegPath,
            '-i', $inputPath,
            '-c:v', $config['codec'] ?? 'libx264',
            '-b:v', $config['bitrate'],
            '-r', (string)$config['framerate'],
            '-profile:v', $config['profile'] ?? 'main',
            '-level', $config['level'] ?? '4.0',
            '-s', "{$config['width']}x{$config['height']}",
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            '-y', // Overwrite output file
            $fullOutputPath
        ];
    }


}
