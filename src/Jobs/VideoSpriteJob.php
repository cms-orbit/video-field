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
    public function __construct(Video $video, int $frames = 100, int $columns = 10, int $rows = 10, bool $force = false)
    {
        $this->video = $video;
        $this->frames = $frames;
        $this->columns = $columns;
        $this->rows = $rows;
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
            // Check if sprite already exists
            if ($this->video->getAttribute('scrubbing_sprite_path') && !$this->force) {
                $disk = config('orbit-video.storage.disk');
                if (Storage::disk($disk)->exists($this->video->getAttribute('scrubbing_sprite_path'))) {
                    Log::info("Sprite already exists for video: {$this->video->getAttribute('id')}");
                    return true;
                }
            }

            // Check if FFmpeg is available
            if (!$this->checkFFmpeg()) {
                $this->logJobError('sprite generation', $this->video->getAttribute('id'), 'FFmpeg not found');
                return false;
            }

            // Find original file
            try {
                $originalPath = $this->video->getVideoPath();
            } catch (Exception $e) {
                $this->logJobError('sprite generation', $this->video->getAttribute('id'), 'Failed to get video path: ' . $e->getMessage());
                return false;
            }

            if (!$originalPath || !file_exists($originalPath)) {
                $this->logJobError('sprite generation', $this->video->getAttribute('id'), 'Original video file not found at: ' . $originalPath);
                return false;
            }

            // Check video duration
            $duration = $this->video->getAttribute('duration');
            if (!$duration || $duration <= 0) {
                $this->logJobError('sprite generation', $this->video->getAttribute('id'), 'Video duration not available');
                return false;
            }

            // Calculate sprite parameters
            $totalFrames = min($this->frames, $this->columns * $this->rows);
            $interval = $duration / ($totalFrames + 1); // +1 to avoid capturing at exact end

            Log::info("Sprite parameters - Duration: {$duration}s, Frames: {$totalFrames}, Interval: " . round($interval, 2) . "s");

            // Generate sprite
            $spritePath = $this->generateSpriteSheet($originalPath, $totalFrames, $interval);

            if ($spritePath) {
                // Generate sprite metadata
                $spriteMetadata = $this->generateSpriteMetadata($spritePath, $totalFrames, $interval);
                
                // Save metadata to JSON file
                $metadataPath = $this->saveSpriteMetadata($spriteMetadata);

                // Update video record with metadata path
                $this->video->update([
                    'scrubbing_sprite_path' => $metadataPath,
                ]);

                Log::info("Sprite saved to: {$spritePath}");
                Log::info("Sprite metadata saved to: {$metadataPath}");
                return true;
            } else {
                Log::error("Failed to generate sprite sheet");
                return false;
            }

        } catch (Exception $e) {
            Log::error("Exception in sprite generation: " . $e->getMessage());
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
            if ($this->generateSpriteWithTileFilter($originalPath, $fullSpritePath, $totalFrames, $interval, $frameWidth, $frameHeight, $quality, $format)) {
                $fileSize = filesize($fullSpritePath);
                Log::info("Sprite generated using tile filter ({$this->formatFileSize($fileSize)})");
                return $spritePath;
            }

            // Method 2: Fallback to frame extraction and composition
            Log::info("Tile filter failed, trying frame extraction method...");
            if ($this->generateSpriteWithFrameExtraction($originalPath, $fullSpritePath, $totalFrames, $interval, $frameWidth, $frameHeight, $quality, $format)) {
                $fileSize = filesize($fullSpritePath);
                Log::info("Sprite generated using frame extraction ({$this->formatFileSize($fileSize)})");
                return $spritePath;
            }

            return null;

        } catch (Exception $e) {
            Log::error("Exception generating sprite: " . $e->getMessage());
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

            Log::info("Executing tile filter command: " . implode(' ', $command));

            // Execute FFmpeg
            $process = new Process($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if ($process->isSuccessful() && file_exists($outputPath)) {
                return true;
            } else {
                Log::info("Tile filter error: " . $process->getErrorOutput());
                return false;
            }

        } catch (Exception $e) {
            Log::info("Tile filter exception: " . $e->getMessage());
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
        $metadataPath = "videos/{$videoId}/sprites/sprite_metadata.json";
        
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
