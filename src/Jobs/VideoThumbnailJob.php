<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Traits\VideoJobTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Exception;

class VideoThumbnailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, VideoJobTrait;

    public $timeout = 300; // 5 minutes
    public $tries = 2;

    protected Video $video;
    protected int $captureTime;
    protected bool $force;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, ?int $captureTime = null, bool $force = false)
    {
        $this->video = $video;
        $this->captureTime = $captureTime ?? config('orbit-video.thumbnails.time_position', 5);
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
            $this->logJobStart('thumbnail generation', $videoId);

            $success = $this->generateThumbnail();

            if ($success) {
                $this->logJobCompletion('thumbnail generation', $videoId);
            } else {
                $this->logJobError('thumbnail generation', $videoId, 'Thumbnail generation process failed');
            }

        } catch (Exception $e) {
            $this->logJobError('thumbnail generation', $this->video->getAttribute('id'), $e->getMessage(), [
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
        Log::error("Thumbnail generation job failed for video: {$this->video->getAttribute('id')}", [
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Generate thumbnail for the video.
     */
    private function generateThumbnail(): bool
    {
        try {
            $videoId = $this->video->getAttribute('id');

            // Check if thumbnail already exists
            if ($this->video->getAttribute('thumbnail_path') && !$this->force) {
                $disk = config('orbit-video.storage.disk');
                $existingPath = $this->video->getAttribute('thumbnail_path');
                if (Storage::disk($disk)->exists($existingPath)) {
                    $this->logInfo("Thumbnail already exists, skipping", [
                        'video_id' => $videoId,
                        'thumbnail_path' => $existingPath,
                    ]);
                    return true;
                }
            }

            // Check if FFmpeg is available
            $this->logDebug("Checking FFmpeg availability for thumbnail generation", ['video_id' => $videoId]);
            if (!$this->checkFFmpeg()) {
                $this->logJobError('thumbnail generation', $videoId, 'FFmpeg not found - please install FFmpeg');
                return false;
            }

            // Find original file
            try {
                $this->logDebug("Getting video path for thumbnail", ['video_id' => $videoId]);
                $originalPath = $this->video->getVideoPath();
            } catch (Exception $e) {
                $this->logJobError('thumbnail generation', $videoId, 'Failed to get video path: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                return false;
            }

            if (!$originalPath || !file_exists($originalPath)) {
                $this->logJobError('thumbnail generation', $videoId, 'Original video file not found', [
                    'expected_path' => $originalPath,
                    'file_exists' => file_exists($originalPath ?? ''),
                ]);
                return false;
            }

            $this->logInfo("Original video file found for thumbnail", [
                'video_id' => $videoId,
                'path' => $originalPath,
            ]);

            // Validate capture time against video duration
            $captureTime = $this->captureTime;
            $duration = $this->video->getAttribute('duration');

            if ($duration && $captureTime >= $duration) {
                $captureTime = max(1, (int)($duration / 2));
                $this->logInfo("Adjusted capture time to fit video duration", [
                    'video_id' => $videoId,
                    'original_capture_time' => $this->captureTime . 's',
                    'adjusted_capture_time' => $captureTime . 's',
                    'video_duration' => $duration . 's',
                ]);
            } else {
                $this->logDebug("Using capture time: {$captureTime}s", [
                    'video_id' => $videoId,
                    'capture_time' => $captureTime . 's',
                ]);
            }

            // Generate thumbnail
            $this->logDebug("Generating thumbnail image", [
                'video_id' => $videoId,
                'capture_time' => $captureTime . 's',
            ]);

            $thumbnailPath = $this->generateThumbnailImage($originalPath, $captureTime);

            if ($thumbnailPath) {
                // Update video record with relative path
                $this->video->update(['thumbnail_path' => $thumbnailPath]);
                $this->logInfo("Thumbnail generated and saved successfully", [
                    'video_id' => $videoId,
                    'thumbnail_path' => $thumbnailPath,
                ]);
                return true;
            } else {
                $this->logJobError('thumbnail generation', $videoId, 'Failed to generate thumbnail image');
                return false;
            }

        } catch (Exception $e) {
            $this->logJobError('thumbnail generation', $this->video->getAttribute('id'), "Exception: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Generate thumbnail image using FFmpeg.
     */
    private function generateThumbnailImage(string $originalPath, int $captureTime): ?string
    {
        try {
            // Get thumbnail configuration
            $thumbnailConfig = config('orbit-video.thumbnails');
            $format = $thumbnailConfig['format'] ?? 'jpeg';
            $quality = $thumbnailConfig['quality'] ?? 85;

            // Generate thumbnail path
            $thumbnailDir = $this->video->getThumbnailPath();
            $thumbnailFilename = "thumbnail.{$format}";
            $thumbnailPath = $thumbnailDir . '/' . $thumbnailFilename;

            // Get full paths
            $disk = config('orbit-video.storage.disk');
            $fullThumbnailPath = Storage::disk($disk)->path($thumbnailPath);

            // Ensure thumbnail directory exists
            $thumbnailDirectory = dirname($fullThumbnailPath);
            $this->ensureDirectoryExists($thumbnailDirectory);

            // Build FFmpeg command
            $command = $this->buildThumbnailCommand($originalPath, $fullThumbnailPath, $captureTime, $quality, $format);

            $this->logFFmpegCommand($command, [
                'video_id' => $this->video->getAttribute('id'),
                'format' => $format,
                'quality' => $quality,
                'capture_time' => $captureTime . 's',
            ]);

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(60); // 1 minute timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Verify file was created
                if (file_exists($fullThumbnailPath)) {
                    $fileSize = filesize($fullThumbnailPath);
                    $this->logFFmpegResult(true, $process->getOutput(), '', [
                        'video_id' => $this->video->getAttribute('id'),
                        'output_path' => $thumbnailPath,
                        'file_size' => $this->formatFileSize($fileSize),
                        'format' => $format,
                    ]);
                    return $thumbnailPath;
                } else {
                    $this->logJobError('thumbnail generation', $this->video->getAttribute('id'), 'Thumbnail file was not created by FFmpeg');
                    return null;
                }
            } else {
                $this->logFFmpegResult(false, $process->getOutput(), $process->getErrorOutput(), [
                    'video_id' => $this->video->getAttribute('id'),
                ]);
                return null;
            }

        } catch (Exception $e) {
            $this->logJobError('thumbnail generation', $this->video->getAttribute('id'), "Exception: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Build FFmpeg command for thumbnail generation.
     */
    private function buildThumbnailCommand(string $inputPath, string $outputPath, int $captureTime, int $quality, string $format): array
    {
        $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');

        $command = [
            $ffmpegPath,
            '-i', $inputPath,
            '-ss', (string)$captureTime,
            '-vframes', '1',
            '-f', 'image2',
        ];

        // Add format-specific options
        if ($format === 'jpeg') {
            $command[] = '-q:v';
            $command[] = (string)((100 - $quality) / 4); // Convert quality to FFmpeg scale (2-31)
        } elseif ($format === 'webp') {
            $command[] = '-c:v';
            $command[] = 'libwebp';
            $command[] = '-quality';
            $command[] = (string)$quality;
        } else {
            $command[] = '-quality';
            $command[] = (string)$quality;
        }

        // Scale to reasonable size if needed
        $command[] = '-vf';
        $command[] = 'scale=1920:1080:force_original_aspect_ratio=decrease';

        $command[] = '-y'; // Overwrite output file
        $command[] = $outputPath;

        return $command;
    }


}
