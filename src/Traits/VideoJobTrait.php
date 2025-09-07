<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Traits;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

trait VideoJobTrait
{
    /**
     * Check if FFmpeg is available.
     */
    protected function checkFFmpeg(): bool
    {
        $ffmpegPath = config('orbit-video.ffmpeg.binary_path', 'ffmpeg');
        $process = new Process([$ffmpegPath, '-version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Check if FFprobe is available.
     */
    protected function checkFFprobe(): bool
    {
        $ffprobePath = config('orbit-video.ffmpeg.ffprobe_path', 'ffprobe');
        $process = new Process([$ffprobePath, '-version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Format file size for display.
     */
    protected function formatFileSize(int $bytes): string
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

    /**
     * Get video metadata using FFprobe.
     */
    protected function getVideoMetadata(string $filePath): ?array
    {
        if (!$this->checkFFprobe()) {
            Log::error('FFprobe not found');
            return null;
        }

        $ffprobePath = config('orbit-video.ffmpeg.ffprobe_path', 'ffprobe');

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
            Log::error('FFprobe failed: ' . $process->getErrorOutput());
            return null;
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        if (!$data) {
            Log::error('Failed to parse FFprobe output');
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
            Log::error('No video stream found');
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
    protected function parseFramerate(string $framerate): ?float
    {
        if (strpos($framerate, '/') !== false) {
            [$num, $den] = explode('/', $framerate);
            return $den > 0 ? $num / $den : null;
        }

        return (float)$framerate ?: null;
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectoryExists(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }

    /**
     * Log job start with context.
     */
    protected function logJobStart(string $jobName, int $videoId, array $context = []): void
    {
        Log::info("Starting {$jobName} for video: {$videoId}", $context);
    }

    /**
     * Log job completion with context.
     */
    protected function logJobCompletion(string $jobName, int $videoId, array $context = []): void
    {
        Log::info("Completed {$jobName} for video: {$videoId}", $context);
    }

    /**
     * Log job error with context.
     */
    protected function logJobError(string $jobName, int $videoId, string $error, array $context = []): void
    {
        Log::error("Error in {$jobName} for video: {$videoId} - {$error}", $context);
    }
}
