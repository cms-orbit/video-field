<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait VideoStorageTrait
{
    /**
     * Get human readable file size.
     */
    public function getReadableSize(): string
    {
        $bytes = $this->getAttribute('file_size') ?? $this->getAttribute('original_size');
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes ?? 0, 2) . ' ' . $units[$i];
    }

    /**
     * Get storage disk instance.
     */
    protected function getStorageDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('orbit-video.storage.disk'));
    }

    /**
     * Generate URL from storage path.
     */
    protected function generateStorageUrl(?string $path): ?string
    {
        return $path ? $this->getStorageDisk()->url($path) : null;
    }

    /**
     * Check if file exists in storage.
     */
    protected function storageFileExists(?string $path): bool
    {
        return !empty($path) && $this->getStorageDisk()->exists($path);
    }

    /**
     * Replace videoId placeholder in path.
     */
    protected function replaceVideoIdInPath(string $basePath, int|string $videoId): string
    {
        return Str::replace('{videoId}', (string) $videoId, $basePath);
    }
}
