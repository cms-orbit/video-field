<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Storage;
use CmsOrbit\VideoField\Traits\VideoStorageTrait;

/**
 * @property Video $video
 */
class VideoProfile extends Model
{
    use HasUuids, VideoStorageTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'video_profiles';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [
        'id',
        'uuid',
        'created_at',
        'updated_at',
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
        return $this->generateStorageUrl($this->getAttribute('path'));
    }

    /**
     * Generate profile path for this video profile.
     */
    public function generateProfilePath(): string
    {
        $videoId = $this->video->getAttribute('id');
        $profileName = $this->getAttribute('profile');
        $extension = 'mp4'; // Default extension for video profiles

        $basePath = config('orbit-video.storage.profiles_path', 'videos/{videoId}/profiles');
        $path = $this->replaceVideoIdInPath($basePath, $videoId);

        return "{$path}/{$profileName}.{$extension}";
    }

    /**
     * Generate HLS directory path for this video profile.
     */
    public function generateHlsDirectory(): string
    {
        $videoId = $this->video->getAttribute('id');
        $profileName = $this->getAttribute('profile');

        $basePath = config('orbit-video.storage.hls_path', 'videos/{videoId}/hls');
        $path = $this->replaceVideoIdInPath($basePath, $videoId);

        return "{$path}/{$profileName}";
    }

    /**
     * Generate DASH directory path for this video profile.
     */
    public function generateDashDirectory(): string
    {
        $videoId = $this->video->getAttribute('id');
        $profileName = $this->getAttribute('profile');

        $basePath = config('orbit-video.storage.dash_path', 'videos/{videoId}/dash');
        $path = $this->replaceVideoIdInPath($basePath, $videoId);

        return "{$path}/{$profileName}";
    }

    /**
     * Get HLS playlist URL for this profile.
     */
    public function getHlsUrl(): ?string
    {
        $path = $this->getAttribute('hls_path');
        return $path ? $this->generateStorageUrl($path) : null;
    }

    /**
     * Get DASH manifest URL for this profile.
     */
    public function getDashUrl(): ?string
    {
        $path = $this->getAttribute('dash_path');
        return $path ? $this->generateStorageUrl($path) : null;
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

    /**
     * Check if progressive download should be exported.
     */
    public function shouldExportProgressive(): bool
    {
        return $this->getEncodingConfig()['export_progressive'] ?? true;
    }

    /**
     * Check if HLS should be exported.
     */
    public function shouldExportHls(): bool
    {
        return $this->getEncodingConfig()['export_hls'] ?? true;
    }

    /**
     * Check if DASH should be exported.
     */
    public function shouldExportDash(): bool
    {
        return $this->getEncodingConfig()['export_dash'] ?? true;
    }

    /**
     * Get encoding configuration for this video profile.
     * First tries to get config from related models, then falls back to config default.
     */
    protected function getEncodingConfig(): array
    {
        // First, try to get encoding config from related models that use HasVideos trait
        $relatedModels = $this->video->relatedModels()->with('model')->get();

        foreach ($relatedModels as $relation) {
            $model = $relation->model;
            if ($model && method_exists($model, 'getVideoEncodingSettings')) {
                $modelConfig = $model->getVideoEncodingSettings();
                if (!empty($modelConfig)) {
                    return $modelConfig;
                }
            }
        }

        // Fall back to config default
        return config('orbit-video.default_encoding', []);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::deleted(function (VideoProfile $profile) {
            $disk = config('orbit-video.storage.disk', 'public');
            $videoPath = $profile->getAttribute('path');
            if (Storage::disk($disk)->exists($videoPath)) {
                Storage::disk($disk)->deleteDirectory($videoPath);
            }
        });
    }
}
