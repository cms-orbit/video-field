<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;
use CmsOrbit\VideoField\Entities\Video\VideoEncodingLog;
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
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 3600; // 1 hour
    public $tries = 3;

    protected Video $video;
    protected ?string $profileFilter;
    protected bool $force;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, ?string $profileFilter = null, bool $force = false)
    {
        $this->video = $video;
        $this->profileFilter = $profileFilter;
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
            Log::info("Starting video encoding job for video: {$this->video->getAttribute('id')}");

            // Update video status
            $this->video->update(['status' => 'encoding']);

            // Perform encoding
            $success = $this->encodeVideo();

            if ($success) {
                $this->video->update(['status' => 'completed']);
                Log::info("Video encoding completed successfully for video: {$this->video->getAttribute('id')}");
            } else {
                $this->video->update(['status' => 'failed']);
                Log::error("Video encoding failed for video: {$this->video->getAttribute('id')}");
            }

        } catch (Exception $e) {
            $this->video->update(['status' => 'failed']);
            Log::error("Video encoding job exception for video: {$this->video->getAttribute('id')}", [
                'error' => $e->getMessage(),
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
            Log::error('FFmpeg not found');
            return false;
        }

        // Find original file
        $originalPath = $this->findOriginalFile();
        if (!$originalPath) {
            Log::error('Original video file not found');
            return false;
        }

        // Get video metadata
        $metadata = $this->getVideoMetadata($originalPath);
        if (!$metadata) {
            Log::error('Failed to extract video metadata');
            return false;
        }

        // Update video with metadata
        $this->video->update([
            'duration' => $metadata['duration'] ?? null,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'framerate' => $metadata['framerate'] ?? null,
            'bitrate' => $metadata['bitrate'] ?? null,
        ]);

        // Get profiles to encode
        $allProfiles = config('video.profiles', []);
        $suitableProfiles = $this->selectSuitableProfiles($metadata, $allProfiles);

        if (empty($suitableProfiles)) {
            Log::warning('No suitable profiles found for encoding');
            return true; // Not an error, just no encoding needed
        }

        $successCount = 0;
        foreach ($suitableProfiles as $profileName => $profileConfig) {
            if ($this->encodeProfile($profileName, $profileConfig, $originalPath, $metadata)) {
                $successCount++;
            }
        }

        $totalProfiles = count($suitableProfiles);
        Log::info("Encoding completed: {$successCount}/{$totalProfiles} profiles successful");

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
                $disk = config('video.storage.disk');
                if (Storage::disk($disk)->exists($existingProfile->generateProfilePath())) {
                    Log::info("Profile {$profileName} already exists, skipping");
                    return true;
                }
            }

            // Create or update video profile record
            $videoProfile = VideoProfile::updateOrCreate(
                [
                    'video_id' => $this->video->getAttribute('id'),
                    'profile' => $profileName,
                ],
                [
                    'status' => 'encoding',
                    'started_at' => now(),
                ]
            );

            // Create encoding log
            $encodingLog = $videoProfile->encodingLogs()->create([
                'status' => 'started',
                'started_at' => now(),
                'ffmpeg_command' => 'Building command...',
            ]);

            // Build output path
            $outputPath = $videoProfile->generateProfilePath();
            $command = $this->buildFFmpegCommand($originalPath, $outputPath, $profileConfig);

            // Update log with actual command
            $encodingLog->update(['ffmpeg_command' => implode(' ', $command)]);

            Log::info("Starting encoding for profile: {$profileName}");

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Get output file size
                $disk = config('video.storage.disk');
                $fileSize = Storage::disk($disk)->size($outputPath);

                // Update profile and log
                $videoProfile->update([
                    'status' => 'completed',
                    'file_size' => $fileSize,
                    'completed_at' => now(),
                ]);

                $encodingLog->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'file_size' => $fileSize,
                ]);

                Log::info("Profile {$profileName} encoded successfully ({$this->formatFileSize($fileSize)})");
                return true;

            } else {
                $errorOutput = $process->getErrorOutput();

                $videoProfile->update(['status' => 'failed']);
                $encodingLog->update([
                    'status' => 'failed',
                    'error_message' => $errorOutput,
                    'completed_at' => now(),
                ]);

                Log::error("Profile {$profileName} encoding failed: {$errorOutput}");
                return false;
            }

        } catch (Exception $e) {
            if (isset($videoProfile)) {
                $videoProfile->update(['status' => 'failed']);
            }
            if (isset($encodingLog)) {
                $encodingLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }

            Log::error("Exception encoding profile {$profileName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if FFmpeg is available.
     */
    private function checkFFmpeg(): bool
    {
        $ffmpegPath = config('video.ffmpeg.binary_path', 'ffmpeg');
        $process = new Process([$ffmpegPath, '-version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Find the original video file.
     */
    private function findOriginalFile(): ?string
    {
        $disk = config('video.storage.disk');
        $videoPath = $this->video->getVideoPath();

        $patterns = [
            $videoPath . '/original_' . $this->video->getAttribute('original_filename'),
            $videoPath . '/' . $this->video->getAttribute('original_filename'),
        ];

        foreach ($patterns as $pattern) {
            if (Storage::disk($disk)->exists($pattern)) {
                return Storage::disk($disk)->path($pattern);
            }
        }

        return null;
    }

    /**
     * Get video metadata using FFprobe.
     */
    private function getVideoMetadata(string $filePath): ?array
    {
        $ffprobePath = config('video.ffmpeg.ffprobe_path', 'ffprobe');

        $command = [
            $ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $filePath
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        if (!$data) {
            return null;
        }

        // Find video stream
        $videoStream = null;
        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        if (!$videoStream) {
            return null;
        }

        return [
            'duration' => (float)($data['format']['duration'] ?? 0),
            'width' => (int)($videoStream['width'] ?? 0),
            'height' => (int)($videoStream['height'] ?? 0),
            'framerate' => $this->parseFramerate($videoStream['r_frame_rate'] ?? ''),
            'bitrate' => (int)($data['format']['bit_rate'] ?? 0),
        ];
    }

    /**
     * Parse framerate from FFprobe output.
     */
    private function parseFramerate(string $framerate): ?float
    {
        if (strpos($framerate, '/') !== false) {
            [$num, $den] = explode('/', $framerate);
            return $den > 0 ? $num / $den : null;
        }

        return (float)$framerate ?: null;
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

        foreach ($allProfiles as $profileName => $config) {
            // Apply profile filter if specified
            if ($this->profileFilter && $profileName !== $this->profileFilter) {
                continue;
            }

            $profileWidth = $config['width'] ?? 0;
            $profileHeight = $config['height'] ?? 0;
            $profileFramerate = $config['framerate'] ?? 30;

            // Skip if profile resolution is higher than original
            if ($profileWidth > $originalWidth || $profileHeight > $originalHeight) {
                continue;
            }

            // Skip if profile framerate is higher than original
            if ($profileFramerate > $originalFramerate) {
                continue;
            }

            $suitable[$profileName] = $config;
        }

        return $suitable;
    }

    /**
     * Build FFmpeg command for encoding.
     */
    private function buildFFmpegCommand(string $inputPath, string $outputPath, array $config): array
    {
        $ffmpegPath = config('video.ffmpeg.binary_path', 'ffmpeg');
        $disk = config('video.storage.disk');
        $fullOutputPath = Storage::disk($disk)->path($outputPath);

        // Ensure output directory exists
        $outputDir = dirname($fullOutputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

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

    /**
     * Format file size for display.
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unitIndex = 0;

        while ($size > 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}
