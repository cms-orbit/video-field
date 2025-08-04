<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Storage;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoEncodingLog;

class VideoProfile extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'video_profiles';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'video_id',
        'field',
        'profile',
        'path',
        'encoded',
        'file_size',
        'width',
        'height',
        'framerate',
        'bitrate',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'encoded' => 'boolean',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'framerate' => 'integer',
    ];

    /**
     * Get the unique identifier column names.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the video that owns this profile.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the encoding logs for this profile.
     */
    public function encodingLogs(): HasMany
    {
        return $this->hasMany(VideoEncodingLog::class);
    }

    /**
     * Get the video URL for this profile.
     */
    public function getUrl(): ?string
    {
        return $this->path ? Storage::disk(config('video.storage.disk'))->url($this->path) : null;
    }

    /**
     * Generate profile file path with videoId placeholder.
     */
    public function generateProfilePath(): string
    {
        $videoId = $this->video?->getAttribute('id') ?? $this->getAttribute('video_id');
        $videoPath = config('video.storage.video_path');
        $basePath = \Str::replace('{videoId}', (string) $videoId, $videoPath);

        return $basePath . '/' . $this->getAttribute('profile') . '.mp4';
    }

    /**
     * Get human readable file size.
     */
    public function getReadableSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get profile resolution as string.
     */
    public function getResolution(): string
    {
        return "{$this->width}x{$this->height}";
    }

    /**
     * Get profile quality label.
     */
    public function getQualityLabel(): string
    {
        if ($this->width >= 3840) {
            return '4K';
        } elseif ($this->width >= 1920) {
            return 'FHD';
        } elseif ($this->width >= 1280) {
            return 'HD';
        } else {
            return 'SD';
        }
    }

    /**
     * Check if encoding is in progress.
     */
    public function isEncoding(): bool
    {
        return !$this->encoded && $this->encodingLogs()
            ->where('status', 'started')
            ->exists();
    }

    /**
     * Get latest encoding log.
     */
    public function getLatestLog(): ?VideoEncodingLog
    {
        return $this->encodingLogs()->latest()->first();
    }
}
