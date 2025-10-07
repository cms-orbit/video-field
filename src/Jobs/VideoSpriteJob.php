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

class VideoSpriteJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, VideoJobTrait;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    protected Video $video;
    protected int $frames;
    protected int $columns;
    protected int $rows;
    protected bool $force;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, ?int $frames = null, ?int $columns = null, ?int $rows = null, bool $force = false)
    {
        $spriteConfig = config('orbit-video.sprites', []);

        $this->video = $video;
        $this->frames = $frames ?? 100;
        $this->columns = $columns ?? ($spriteConfig['columns'] ?? 10);
        $this->rows = $rows ?? ($spriteConfig['rows'] ?? 10);
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
            $this->logJobStart('sprite generation', $videoId);

            $success = $this->generateSprite();

            if ($success) {
                $this->logJobCompletion('sprite generation', $videoId);
            } else {
                $this->logJobError('sprite generation', $videoId, 'Sprite generation process failed');
            }

        } catch (Exception $e) {
            $this->logJobError('sprite generation', $this->video->getAttribute('id'), $e->getMessage(), [
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
        Log::error("Sprite generation job failed for video: {$this->video->getAttribute('id')}", [
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Generate sprite sheet for the video.
     */
    private function generateSprite(): bool
    {
        try {
            $videoId = $this->video->getAttribute('id');

            // Check if sprite already exists
            if ($this->video->getAttribute('scrubbing_sprite_path') && !$this->force) {
                $disk = config('orbit-video.storage.disk');
                $existingPath = $this->video->getAttribute('scrubbing_sprite_path');
                if (Storage::disk($disk)->exists($existingPath)) {
                    $this->logInfo("Sprite already exists, skipping", [
                        'video_id' => $videoId,
                        'sprite_path' => $existingPath,
                    ]);
                    return true;
                }
            }

            // Check if FFmpeg is available
            $this->logDebug("Checking FFmpeg availability for sprite generation", ['video_id' => $videoId]);
            if (!$this->checkFFmpeg()) {
                $this->logJobError('sprite generation', $videoId, 'FFmpeg not found - please install FFmpeg');
                return false;
            }

            // Find original file
            try {
                $this->logDebug("Getting video path for sprite", ['video_id' => $videoId]);
                $originalPath = $this->video->getVideoPath();
            } catch (Exception $e) {
                $this->logJobError('sprite generation', $videoId, 'Failed to get video path: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                return false;
            }

            if (!$originalPath || !file_exists($originalPath)) {
                $this->logJobError('sprite generation', $videoId, 'Original video file not found', [
                    'expected_path' => $originalPath,
                    'file_exists' => file_exists($originalPath ?? ''),
                ]);
                return false;
            }

            $this->logInfo("Original video file found for sprite", [
                'video_id' => $videoId,
                'path' => $originalPath,
            ]);

            // Check video duration
            $duration = $this->video->getAttribute('duration');
            if (!$duration || $duration <= 0) {
                $this->logJobError('sprite generation', $videoId, 'Video duration not available or invalid', [
                    'duration' => $duration,
                ]);
                return false;
            }

            // Calculate sprite parameters
            $totalFrames = min($this->frames, $this->columns * $this->rows);
            $interval = $duration / ($totalFrames + 1); // +1 to avoid capturing at exact end

            $this->logInfo("Sprite parameters calculated", [
                'video_id' => $videoId,
                'duration' => $duration . 's',
                'total_frames' => $totalFrames,
                'interval' => round($interval, 2) . 's',
                'grid' => "{$this->columns}x{$this->rows}",
            ]);

            // Generate sprite
            $this->logDebug("Generating sprite sheet", [
                'video_id' => $videoId,
                'total_frames' => $totalFrames,
            ]);

            $spritePath = $this->generateSpriteSheet($originalPath, $totalFrames, $interval);

            if ($spritePath) {
                // Generate sprite metadata
                $this->logDebug("Generating sprite metadata", ['video_id' => $videoId]);
                $spriteMetadata = $this->generateSpriteMetadata($spritePath, $totalFrames, $interval);

                // Save metadata to JSON file
                $metadataPath = $this->saveSpriteMetadata($spriteMetadata);

                // Update video record with metadata path
                $this->video->update([
                    'scrubbing_sprite_path' => $metadataPath,
                ]);

                $this->logInfo("Sprite generated and saved successfully", [
                    'video_id' => $videoId,
                    'sprite_path' => $spritePath,
                    'metadata_path' => $metadataPath,
                ]);
                return true;
            } else {
                $this->logJobError('sprite generation', $videoId, 'Failed to generate sprite sheet');
                return false;
            }

        } catch (Exception $e) {
            $this->logJobError('sprite generation', $this->video->getAttribute('id'), "Exception: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Generate sprite sheet using FFmpeg.
     */
    private function generateSpriteSheet(string $originalPath, int $totalFrames, float $interval): ?string
    {
        try {
            // Get sprite configuration
            $spriteConfig = config('orbit-video.sprites');
            $frameWidth = $spriteConfig['width'] ?? 160;
            $frameHeight = $spriteConfig['height'] ?? 90;
            $quality = $spriteConfig['quality'] ?? 70;
            $format = $spriteConfig['format'] ?? 'jpeg';

            // Generate sprite path
            $spriteDir = $this->video->getSpritePath();
            $spriteFilename = "sprite.{$format}";
            $spritePath = $spriteDir . '/' . $spriteFilename;

            // Get full paths
            $disk = config('orbit-video.storage.disk');
            $fullSpritePath = Storage::disk($disk)->path($spritePath);

            // Ensure sprite directory exists
            $spriteDirectory = dirname($fullSpritePath);
            $this->ensureDirectoryExists($spriteDirectory);

            // Method 1: Try FFmpeg tile filter (faster)
            $this->logDebug("Attempting sprite generation with tile filter", [
                'video_id' => $this->video->getAttribute('id'),
            ]);

            if ($this->generateSpriteWithTileFilter($originalPath, $fullSpritePath, $totalFrames, $interval, $frameWidth, $frameHeight, $quality, $format)) {
                $fileSize = filesize($fullSpritePath);
                $this->logInfo("Sprite generated using tile filter", [
                    'video_id' => $this->video->getAttribute('id'),
                    'file_size' => $this->formatFileSize($fileSize),
                    'method' => 'tile_filter',
                ]);
                return $spritePath;
            }

            // Method 2: Fallback to frame extraction and composition
            $this->logWarning("Tile filter failed, trying frame extraction method", [
                'video_id' => $this->video->getAttribute('id'),
            ]);

            if ($this->generateSpriteWithFrameExtraction($originalPath, $fullSpritePath, $totalFrames, $interval, $frameWidth, $frameHeight, $quality, $format)) {
                $fileSize = filesize($fullSpritePath);
                $this->logInfo("Sprite generated using frame extraction", [
                    'video_id' => $this->video->getAttribute('id'),
                    'file_size' => $this->formatFileSize($fileSize),
                    'method' => 'frame_extraction',
                ]);
                return $spritePath;
            }

            $this->logJobError('sprite generation', $this->video->getAttribute('id'), 'Both sprite generation methods failed');
            return null;

        } catch (Exception $e) {
            $this->logJobError('sprite generation', $this->video->getAttribute('id'), "Exception: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Generate sprite using FFmpeg tile filter (method 1).
     */
    private function generateSpriteWithTileFilter(string $inputPath, string $outputPath, int $totalFrames, float $interval, int $frameWidth, int $frameHeight, int $quality, string $format): bool
    {
        try {
            $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');

            // Build command with tile filter
            $command = [
                $ffmpegPath,
                '-i', $inputPath,
                '-vf', "fps=1/{$interval},scale={$frameWidth}:{$frameHeight},tile={$this->columns}x" . $this->rows,
                '-frames:v', '1',
            ];

            // Add format-specific options
            if ($format === 'jpeg') {
                $command[] = '-q:v';
                $command[] = (string)((100 - $quality) / 4);
            } elseif ($format === 'webp') {
                $command[] = '-c:v';
                $command[] = 'libwebp';
                $command[] = '-quality';
                $command[] = (string)$quality;
            }

            $command[] = '-y';
            $command[] = $outputPath;

            $this->logFFmpegCommand($command, [
                'video_id' => $this->video->getAttribute('id'),
                'method' => 'tile_filter',
            ]);

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if ($process->isSuccessful() && file_exists($outputPath)) {
                $this->logFFmpegResult(true, $process->getOutput(), '', [
                    'video_id' => $this->video->getAttribute('id'),
                    'method' => 'tile_filter',
                ]);
                return true;
            } else {
                $this->logFFmpegResult(false, $process->getOutput(), $process->getErrorOutput(), [
                    'video_id' => $this->video->getAttribute('id'),
                    'method' => 'tile_filter',
                ]);
                return false;
            }

        } catch (Exception $e) {
            $this->logDebug("Tile filter exception: " . $e->getMessage(), [
                'video_id' => $this->video->getAttribute('id'),
                'exception' => get_class($e),
            ]);
            return false;
        }
    }

    /**
     * Generate sprite using frame extraction and composition (method 2).
     */
    private function generateSpriteWithFrameExtraction(string $inputPath, string $outputPath, int $totalFrames, float $interval, int $frameWidth, int $frameHeight, int $quality, string $format): bool
    {
        try {
            $disk = config('orbit-video.storage.disk');
            $tempDir = $this->video->getSpritePath() . '/temp_frames';
            $fullTempDir = Storage::disk($disk)->path($tempDir);

            // Create temp directory
            $this->ensureDirectoryExists($fullTempDir);

            // Extract frames
            Log::info("Extracting {$totalFrames} frames...");
            $extractedFrames = $this->extractFrames($inputPath, $fullTempDir, $totalFrames, $interval, $frameWidth, $frameHeight);

            if (count($extractedFrames) < $totalFrames) {
                Log::info("Warning: Only extracted " . count($extractedFrames) . " frames out of {$totalFrames}");
            }

            // Compose sprite sheet
            Log::info("Composing sprite sheet...");
            $success = $this->composeSpriteSheet($extractedFrames, $outputPath, $frameWidth, $frameHeight, $quality, $format);

            // Clean up temp files
            $this->cleanupTempFiles($fullTempDir);

            return $success;

        } catch (Exception $e) {
            Log::error("Frame extraction exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract individual frames from video.
     */
    private function extractFrames(string $inputPath, string $tempDir, int $totalFrames, float $interval, int $frameWidth, int $frameHeight): array
    {
        $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');
        $extractedFrames = [];

        for ($i = 0; $i < $totalFrames; $i++) {
            $time = ($i + 1) * $interval;
            $frameFile = $tempDir . "/frame_" . str_pad($i, 3, '0', STR_PAD_LEFT) . ".jpg";

            $command = [
                $ffmpegPath,
                '-i', $inputPath,
                '-ss', (string)$time,
                '-vframes', '1',
                '-vf', "scale={$frameWidth}:{$frameHeight}",
                '-q:v', '2',
                '-y',
                $frameFile
            ];

            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful() && file_exists($frameFile)) {
                $extractedFrames[] = $frameFile;
            }
        }

        return $extractedFrames;
    }

    /**
     * Compose sprite sheet from individual frames.
     */
    private function composeSpriteSheet(array $frameFiles, string $outputPath, int $frameWidth, int $frameHeight, int $quality, string $format): bool
    {
        try {
            $rows = ceil(count($frameFiles) / $this->columns);
            $spriteWidth = $frameWidth * $this->columns;
            $spriteHeight = $frameHeight * $rows;

            // Create sprite canvas
            $sprite = imagecreatetruecolor($spriteWidth, $spriteHeight);
            $background = imagecolorallocate($sprite, 0, 0, 0);
            imagefill($sprite, 0, 0, $background);

            // Add each frame to sprite
            foreach ($frameFiles as $index => $frameFile) {
                if (!file_exists($frameFile)) continue;

                $frame = imagecreatefromjpeg($frameFile);
                if (!$frame) continue;

                // Calculate position
                $col = $index % $this->columns;
                $row = floor($index / $this->columns);
                $x = $col * $frameWidth;
                $y = $row * $frameHeight;

                // Copy frame to sprite
                imagecopyresampled(
                    $sprite, $frame,
                    $x, $y, 0, 0,
                    $frameWidth, $frameHeight,
                    imagesx($frame), imagesy($frame)
                );

                imagedestroy($frame);
            }

            // Save sprite
            $success = false;
            if ($format === 'webp') {
                $success = imagewebp($sprite, $outputPath, $quality);
            } else {
                $success = imagejpeg($sprite, $outputPath, $quality);
            }

            imagedestroy($sprite);
            return $success;

        } catch (Exception $e) {
            Log::error("Sprite composition error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up temporary files.
     */
    private function cleanupTempFiles(string $tempDir): void
    {
        try {
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($tempDir);
            }
        } catch (Exception $e) {
            Log::info("Warning: Could not clean up temp files: " . $e->getMessage());
        }
    }

    /**
     * Generate sprite metadata with all frame information.
     */
    private function generateSpriteMetadata(string $spritePath, int $totalFrames, float $interval): array
    {
        $spriteConfig = config('orbit-video.sprites');
        $frameWidth = $spriteConfig['width'] ?? 160;
        $frameHeight = $spriteConfig['height'] ?? 90;
        $format = $spriteConfig['format'] ?? 'jpeg';

        // Get sprite file size
        $disk = config('orbit-video.storage.disk');
        $fullSpritePath = Storage::disk($disk)->path($spritePath);
        $fileSize = file_exists($fullSpritePath) ? filesize($fullSpritePath) : 0;

        // Generate frame data
        $frames = [];
        for ($i = 0; $i < $totalFrames; $i++) {
            $time = ($i + 1) * $interval;
            $col = $i % $this->columns;
            $row = floor($i / $this->columns);

            $frames[] = [
                'index' => $i,
                'time' => round($time, 3),
                'position' => [
                    'x' => $col * $frameWidth,
                    'y' => $row * $frameHeight,
                    'width' => $frameWidth,
                    'height' => $frameHeight,
                ],
                'grid' => [
                    'column' => $col,
                    'row' => $row,
                ]
            ];
        }

        return [
            'version' => '1.0',
            'generated_at' => now()->toISOString(),
            'video_id' => $this->video->getAttribute('id'),
            'sprite' => [
                'path' => $spritePath,
                'format' => $format,
                'file_size' => $fileSize,
                'file_size_human' => $this->formatFileSize($fileSize),
            ],
            'grid' => [
                'columns' => $this->columns,
                'rows' => $this->rows,
                'total_frames' => $totalFrames,
                'frame_width' => $frameWidth,
                'frame_height' => $frameHeight,
                'sprite_width' => $this->columns * $frameWidth,
                'sprite_height' => ceil($totalFrames / $this->columns) * $frameHeight,
            ],
            'timing' => [
                'interval' => round($interval, 3),
                'duration' => $this->video->getAttribute('duration'),
                'fps' => round(1 / $interval, 2),
            ],
            'frames' => $frames,
        ];
    }

    /**
     * Save sprite metadata to JSON file.
     */
    private function saveSpriteMetadata(array $metadata): string
    {
        $videoId = $this->video->getAttribute('id');
        $basePath = config('orbit-video.storage.sprites_path', 'videos/{videoId}/sprites');
        $spritesPath = str_replace('{videoId}', (string) $videoId, $basePath);
        $metadataPath = "{$spritesPath}/sprite_metadata.json";

        $disk = config('orbit-video.storage.disk');
        $fullMetadataPath = Storage::disk($disk)->path($metadataPath);

        // Ensure directory exists
        $metadataDir = dirname($fullMetadataPath);
        $this->ensureDirectoryExists($metadataDir);

        // Save JSON file
        file_put_contents($fullMetadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $metadataPath;
    }


}
