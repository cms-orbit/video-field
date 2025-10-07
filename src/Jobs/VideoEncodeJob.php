<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;
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
     * Get profiles for video encoding.
     * First tries to get profiles from related models using HasVideos trait,
     * then falls back to modelProfiles parameter, then config default.
     */
    protected function getProfilesForVideo(): array
    {
        // First, try to get profiles from related models that use HasVideos trait
        $relatedModels = $this->video->relatedModels()->with('model')->get();

        foreach ($relatedModels as $relation) {
            $model = $relation->model;
            if ($model && method_exists($model, 'getAvailableVideoProfiles')) {
                $modelProfiles = $model->getAvailableVideoProfiles();
                if (!empty($modelProfiles)) {
                    $this->logInfo("Using profiles from related model: " . get_class($model), [
                        'model_class' => get_class($model),
                        'profiles' => array_keys($modelProfiles),
                    ]);
                    return $modelProfiles;
                }
            }
        }

        // Fall back to modelProfiles parameter if provided
        if ($this->modelProfiles) {
            $this->logInfo("Using profiles from modelProfiles parameter", [
                'profiles' => $this->modelProfiles,
            ]);
            return $this->modelProfiles;
        }

        // Finally, use config default
        $defaultProfiles = config('orbit-video.default_profiles', []);
        $this->logInfo("Using default profiles from config", [
            'profiles' => array_keys($defaultProfiles),
        ]);
        return $defaultProfiles;
    }

    /**
     * Get encoding configuration for video processing.
     * First tries to get config from related models using HasVideos trait,
     * then falls back to config default.
     */
    protected function getEncodingConfigForVideo(): array
    {
        // First, try to get encoding config from related models that use HasVideos trait
        $relatedModels = $this->video->relatedModels()->with('model')->get();

        foreach ($relatedModels as $relation) {
            $model = $relation->model;
            if ($model && method_exists($model, 'getVideoEncodingSettings')) {
                $modelConfig = $model->getVideoEncodingSettings();
                if (!empty($modelConfig)) {
                    $this->logInfo("Using encoding config from related model: " . get_class($model), [
                        'model_class' => get_class($model),
                        'config' => $modelConfig,
                    ]);
                    return $modelConfig;
                }
            }
        }

        // Fall back to config default
        $defaultConfig = config('orbit-video.default_encoding', []);
        $this->logInfo("Using default encoding config from config", [
            'config' => $defaultConfig,
        ]);
        return $defaultConfig;
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
        $videoId = $this->video->getAttribute('id');

        // Check if FFmpeg is available
        $this->logDebug("Checking FFmpeg availability", ['video_id' => $videoId]);
        if (!$this->checkFFmpeg()) {
            $this->logJobError('video encoding', $videoId, 'FFmpeg not found - please install FFmpeg');
            return false;
        }
        $this->logDebug("FFmpeg is available", ['video_id' => $videoId]);

        // Find original file
        try {
            $this->logDebug("Getting video path", ['video_id' => $videoId]);
            $originalPath = $this->video->getVideoPath();
        } catch (Exception $e) {
            $this->logJobError('video encoding', $videoId, 'Failed to get video path: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }

        if (!$originalPath || !file_exists($originalPath)) {
            $this->logJobError('video encoding', $videoId, 'Original video file not found', [
                'expected_path' => $originalPath,
                'file_exists' => file_exists($originalPath ?? ''),
            ]);
            return false;
        }

        $this->logInfo("Original video file found", [
            'video_id' => $videoId,
            'path' => $originalPath,
            'file_size' => $this->formatFileSize(filesize($originalPath)),
        ]);

        // Get video metadata
        $this->logDebug("Extracting video metadata", ['video_id' => $videoId]);
        $metadata = $this->getVideoMetadata($originalPath);
        if (!$metadata) {
            $this->logJobError('video encoding', $videoId, 'Failed to extract video metadata - file may be corrupted');
            return false;
        }

        $this->logInfo("Video metadata extracted successfully", [
            'video_id' => $videoId,
            'duration' => $metadata['duration'] . 's',
            'resolution' => $metadata['width'] . 'x' . $metadata['height'],
            'framerate' => $metadata['framerate'] . 'fps',
            'bitrate' => $this->formatFileSize($metadata['bitrate']) . '/s',
        ]);

        // Update video with metadata
        $this->video->update([
            'duration' => $metadata['duration'] ?? null,
            'original_width' => $metadata['width'] ?? null,
            'original_height' => $metadata['height'] ?? null,
            'original_framerate' => $metadata['framerate'] ?? null,
            'original_bitrate' => $metadata['bitrate'] ?? null,
        ]);

        // Get profiles to encode - use model profiles if available, otherwise use config
        $allProfiles = $this->getProfilesForVideo();
        $this->logDebug("Selecting suitable profiles for encoding", [
            'video_id' => $videoId,
            'total_available_profiles' => count($allProfiles),
        ]);

        $suitableProfiles = $this->selectSuitableProfiles($metadata, $allProfiles);

        if (empty($suitableProfiles)) {
            $this->logWarning('No suitable profiles found for encoding', [
                'video_id' => $videoId,
                'video_resolution' => $metadata['width'] . 'x' . $metadata['height'],
                'available_profiles' => array_keys($allProfiles),
            ]);
            return true; // Not an error, just no encoding needed
        }

        $this->logInfo("Starting encoding for " . count($suitableProfiles) . " profile(s)", [
            'video_id' => $videoId,
            'profiles' => array_keys($suitableProfiles),
        ]);

        $successCount = 0;
        $failedProfiles = [];

        foreach ($suitableProfiles as $profileName => $profileConfig) {
            $this->logInfo("Starting profile encoding: {$profileName}", [
                'video_id' => $videoId,
                'profile' => $profileName,
                'target_resolution' => $profileConfig['width'] . 'x' . $profileConfig['height'],
                'target_bitrate' => $profileConfig['bitrate'],
            ]);

            if ($this->encodeProfile($profileName, $profileConfig, $originalPath, $metadata)) {
                $successCount++;
            } else {
                $failedProfiles[] = $profileName;
            }
        }

        $totalProfiles = count($suitableProfiles);

        if ($successCount > 0) {
            $this->logJobCompletion('video encoding', $videoId, [
                'successful_profiles' => $successCount,
                'failed_profiles' => count($failedProfiles),
                'total_profiles' => $totalProfiles,
                'failed_profile_names' => $failedProfiles,
            ]);
        } else {
            $this->logJobError('video encoding', $videoId, 'All profiles failed to encode', [
                'total_profiles' => $totalProfiles,
                'failed_profile_names' => $failedProfiles,
            ]);
        }

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
                    $this->logInfo("Profile {$profileName} already exists, skipping", [
                        'profile' => $profileName,
                        'video_id' => $this->video->getAttribute('id'),
                    ]);
                    return true;
                }
            }

            $this->logDebug("Creating or updating video profile record", [
                'profile' => $profileName,
                'video_id' => $this->video->getAttribute('id'),
            ]);

            // Create or update video profile record
            /** @var VideoProfile $videoProfile */
            $videoProfile = VideoProfile::query()->updateOrCreate(
                [
                    'video_id' => $this->video->getAttribute('id'),
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

            // Encode formats based on export options
            $progressiveSuccess = false;
            $hlsSuccess = false;
            $dashSuccess = false;

            $exportFormats = [];
            if ($videoProfile->shouldExportProgressive()) $exportFormats[] = 'progressive';
            if ($videoProfile->shouldExportHls()) $exportFormats[] = 'hls';
            if ($videoProfile->shouldExportDash()) $exportFormats[] = 'dash';

            $this->logInfo("Encoding profile {$profileName} in formats: " . implode(', ', $exportFormats), [
                'profile' => $profileName,
                'formats' => $exportFormats,
                'video_id' => $this->video->getAttribute('id'),
            ]);

            // Encode progressive MP4 if enabled
            if ($videoProfile->shouldExportProgressive()) {
                $this->logDebug("Starting progressive MP4 encoding for {$profileName}");
                $progressiveSuccess = $this->encodeProgressiveProfile($videoProfile, $originalPath, $profileConfig, $encodingLog);
            }

            // Encode HLS if enabled
            if ($videoProfile->shouldExportHls()) {
                $this->logDebug("Starting HLS encoding for {$profileName}");
                $hlsSuccess = $this->encodeHlsProfile($videoProfile, $originalPath, $profileConfig, $encodingLog);
            }

            // Encode DASH if enabled
            if ($videoProfile->shouldExportDash()) {
                $this->logDebug("Starting DASH encoding for {$profileName}");
                $dashSuccess = $this->encodeDashProfile($videoProfile, $originalPath, $profileConfig, $encodingLog);
            }

            if ($progressiveSuccess || $hlsSuccess || $dashSuccess) {
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

                $successFormats = [];
                if ($progressiveSuccess) $successFormats[] = 'progressive';
                if ($hlsSuccess) $successFormats[] = 'hls';
                if ($dashSuccess) $successFormats[] = 'dash';

                $this->logInfo("Profile {$profileName} encoded successfully", [
                    'profile' => $profileName,
                    'video_id' => $this->video->getAttribute('id'),
                    'encoded_formats' => $successFormats,
                ]);
                return true;
            } else {
                $videoProfile->update(['status' => 'failed']);
                $encodingLog->update([
                    'status' => 'error',
                    'error_output' => 'All format encoding failed',
                ]);

                $this->logJobError('profile encoding', $this->video->getAttribute('id'), "Profile {$profileName} encoding failed", [
                    'profile' => $profileName,
                    'progressive_attempted' => $videoProfile->shouldExportProgressive(),
                    'hls_attempted' => $videoProfile->shouldExportHls(),
                    'dash_attempted' => $videoProfile->shouldExportDash(),
                ]);
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

            $this->logJobError('profile encoding', $this->video->getAttribute('id'), "Exception encoding profile {$profileName}: " . $e->getMessage(), [
                'profile' => $profileName,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
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
     * Encode progressive MP4 profile.
     */
    private function encodeProgressiveProfile(VideoProfile $videoProfile, string $originalPath, array $config, $encodingLog): bool
    {
        try {
            $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');
            $disk = config('orbit-video.storage.disk');

            // Progressive MP4 output path
            $mp4Path = $videoProfile->generateProfilePath();
            $fullMp4Path = Storage::disk($disk)->path($mp4Path);
            $this->ensureDirectoryExists(dirname($fullMp4Path));

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
                '-movflags', '+faststart', // Enable progressive download
                '-y', // Overwrite output file
                $fullMp4Path
            ];

            // Update log with actual command
            $encodingLog->update(['ffmpeg_command' => implode(' ', $command)]);

            $this->logFFmpegCommand($command, [
                'profile' => $videoProfile->getAttribute('profile'),
                'format' => 'progressive MP4',
                'video_id' => $this->video->getAttribute('id'),
            ]);

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Update profile with MP4 path and file size
                $fileSize = file_exists($fullMp4Path) ? filesize($fullMp4Path) : null;
                $videoProfile->update([
                    'path' => $mp4Path,
                    'file_size' => $fileSize
                ]);

                $this->logFFmpegResult(true, $process->getOutput(), '', [
                    'profile' => $videoProfile->getAttribute('profile'),
                    'format' => 'progressive MP4',
                    'output_path' => $mp4Path,
                    'file_size' => $this->formatFileSize($fileSize ?? 0),
                    'video_id' => $this->video->getAttribute('id'),
                ]);
                return true;
            } else {
                $errorOutput = $process->getErrorOutput();
                $this->logFFmpegResult(false, $process->getOutput(), $errorOutput, [
                    'profile' => $videoProfile->getAttribute('profile'),
                    'format' => 'progressive MP4',
                    'video_id' => $this->video->getAttribute('id'),
                ]);
                return false;
            }

        } catch (Exception $e) {
            $this->logJobError('progressive encoding', $this->video->getAttribute('id'), "Exception: " . $e->getMessage(), [
                'profile' => $videoProfile->getAttribute('profile'),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
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

            $this->logFFmpegCommand($command, [
                'profile' => $videoProfile->getAttribute('profile'),
                'format' => 'HLS',
                'video_id' => $this->video->getAttribute('id'),
            ]);

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Update profile with HLS path
                $videoProfile->update(['hls_path' => $hlsDir . '/playlist.m3u8']);

                $this->logFFmpegResult(true, $process->getOutput(), '', [
                    'profile' => $videoProfile->getAttribute('profile'),
                    'format' => 'HLS',
                    'output_path' => $hlsDir . '/playlist.m3u8',
                    'video_id' => $this->video->getAttribute('id'),
                ]);
                return true;
            } else {
                $errorOutput = $process->getErrorOutput();
                $this->logFFmpegResult(false, $process->getOutput(), $errorOutput, [
                    'profile' => $videoProfile->getAttribute('profile'),
                    'format' => 'HLS',
                    'video_id' => $this->video->getAttribute('id'),
                ]);
                return false;
            }

        } catch (Exception $e) {
            $this->logJobError('HLS encoding', $this->video->getAttribute('id'), "Exception: " . $e->getMessage(), [
                'profile' => $videoProfile->getAttribute('profile'),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
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

            $this->logFFmpegCommand($command, [
                'profile' => $videoProfile->getAttribute('profile'),
                'format' => 'DASH',
                'video_id' => $this->video->getAttribute('id'),
            ]);

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Update profile with DASH path
                $videoProfile->update(['dash_path' => $dashDir . '/manifest.mpd']);

                $this->logFFmpegResult(true, $process->getOutput(), '', [
                    'profile' => $videoProfile->getAttribute('profile'),
                    'format' => 'DASH',
                    'output_path' => $dashDir . '/manifest.mpd',
                    'video_id' => $this->video->getAttribute('id'),
                ]);
                return true;
            } else {
                $errorOutput = $process->getErrorOutput();
                $this->logFFmpegResult(false, $process->getOutput(), $errorOutput, [
                    'profile' => $videoProfile->getAttribute('profile'),
                    'format' => 'DASH',
                    'video_id' => $this->video->getAttribute('id'),
                ]);
                return false;
            }

        } catch (Exception $e) {
            $this->logJobError('DASH encoding', $this->video->getAttribute('id'), "Exception: " . $e->getMessage(), [
                'profile' => $videoProfile->getAttribute('profile'),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
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
