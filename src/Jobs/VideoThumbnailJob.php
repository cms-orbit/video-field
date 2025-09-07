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
    public function __construct(Video $video, int $captureTime = 5, bool $force = false)
    {
        $this->video = $video;
        $this->captureTime = $captureTime;
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
            // Check if thumbnail already exists
            if ($this->video->getAttribute('thumbnail_path') && !$this->force) {
                $disk = config('orbit-video.storage.disk');
                if (Storage::disk($disk)->exists($this->video->getAttribute('thumbnail_path'))) {
                    Log::info("Thumbnail already exists for video: {$this->video->getAttribute('id')}");
                    return true;
                }
            }

            // Check if FFmpeg is available
            if (!$this->checkFFmpeg()) {
                $this->logJobError('thumbnail generation', $this->video->getAttribute('id'), 'FFmpeg not found');
                return false;
            }

            // Find original file
            try {
                $originalPath = $this->video->getVideoPath();
            } catch (Exception $e) {
                $this->logJobError('thumbnail generation', $this->video->getAttribute('id'), 'Failed to get video path: ' . $e->getMessage());
                return false;
            }

            if (!$originalPath || !file_exists($originalPath)) {
                $this->logJobError('thumbnail generation', $this->video->getAttribute('id'), 'Original video file not found at: ' . $originalPath);
                return false;
            }

            // Validate capture time against video duration
            $captureTime = $this->captureTime;
            if ($this->video->getAttribute('duration') && $captureTime >= $this->video->getAttribute('duration')) {
                $captureTime = max(1, (int)($this->video->getAttribute('duration') / 2));
                Log::info("Adjusted capture time to {$captureTime}s for video: {$this->video->getAttribute('id')}");
            }

            // Generate thumbnail
            $thumbnailPath = $this->generateThumbnailImage($originalPath, $captureTime);

            if ($thumbnailPath) {
                // Update video record
                $this->video->update(['thumbnail_path' => $thumbnailPath]);
                Log::info("Thumbnail saved to: {$thumbnailPath}");
                return true;
            } else {
                Log::error("Failed to generate thumbnail image");
                return false;
            }

        } catch (Exception $e) {
            Log::error("Exception in thumbnail generation: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Find the original video file.
     */
    private function findOriginalFile(): ?string
    {
        $disk = config('orbit-video.storage.disk');
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

            Log::info("Executing FFmpeg command: " . implode(' ', $command));

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(60); // 1 minute timeout
            $process->run();

            if ($process->isSuccessful()) {
                // Verify file was created
                if (file_exists($fullThumbnailPath)) {
                    $fileSize = filesize($fullThumbnailPath);
                    Log::info("Thumbnail generated successfully ({$this->formatFileSize($fileSize)})");
                    return $thumbnailPath;
                } else {
                    Log::error("Thumbnail file was not created");
                    return null;
                }
            } else {
                Log::error("FFmpeg error: " . $process->getErrorOutput());
                return null;
            }

        } catch (Exception $e) {
            Log::error("Exception generating thumbnail: " . $e->getMessage());
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
