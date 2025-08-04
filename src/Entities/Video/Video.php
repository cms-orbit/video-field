<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use App\Services\DynamicModel;
use App\Services\Traits\HasPermissions;
use App\Services\Traits\SettingMenuItemTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;

class Video extends DynamicModel
{
    use SettingMenuItemTrait, HasPermissions;

    /**
     * The table associated with the model.
     */
    protected $table = 'videos';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'original_filename',
        'original_size',
        'duration',
        'original_width',
        'original_height',
        'original_framerate',
        'original_bitrate',
        'thumbnail_path',
        'scrubbing_sprite_path',
        'sprite_columns',
        'sprite_rows',
        'sprite_interval',
        'hls_manifest_path',
        'dash_manifest_path',
        'abr_profiles',
        'mime_type',
        'status',
        'user_id',
        'meta_data',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'duration' => 'integer',
        'original_width' => 'integer',
        'original_height' => 'integer',
        'original_framerate' => 'float',
        'original_bitrate' => 'integer',
        'original_size' => 'integer',
        'sprite_columns' => 'integer',
        'sprite_rows' => 'integer',
        'sprite_interval' => 'integer',
        'user_id' => 'integer',
        'meta_data' => 'array',
        'abr_profiles' => 'array',
    ];


    public static function getMenuSection(): string
    {
        return __('Media');
    }

    public static function getMenuIcon(): string
    {
        return 'bs.play-circle';
    }

    public static function getMenuPriority(): int
    {
        return 5020;
    }

    /**
     * Get the video profiles for this video.
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(VideoProfile::class);
    }

    /**
     * Get all encoding logs through video profiles.
     */
    public function encodingLogs(): HasManyThrough
    {
        return $this->hasManyThrough(VideoEncodingLog::class, VideoProfile::class);
    }

    /**
     * Get models that use this video through polymorphic relationship.
     */
    public function models(): MorphToMany
    {
        return $this->morphedByMany('App\Services\DynamicModel', 'model', 'video_field_relations')
            ->withPivot(['field_name', 'sort_order']);
    }

    /**
     * Get streaming URL for a specific profile.
     */
    public function getStreamingUrl(string $profile): ?string
    {
        $videoProfile = $this->profiles()
            ->where('profile', $profile)
            ->where('encoded', true)
            ->first();

        return $videoProfile ? Storage::disk(config('video.storage.disk'))->url($videoProfile->path) : null;
    }

    /**
     * Get thumbnail URL.
     */
    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk(config('video.storage.disk'))->url($this->thumbnail_path) : null;
    }

    /**
     * Get scrubbing sprite URL.
     */
    public function getScrubbiingSpriteUrl(): ?string
    {
        return $this->scrubbing_sprite_path ? Storage::disk(config('video.storage.disk'))->url($this->scrubbing_sprite_path) : null;
    }

    /**
     * Get path with videoId placeholder replaced.
     */
    public function getVideoPath(): string
    {
        $basePath = config('video.storage.video_path');
        return \Str::replace('{videoId}', (string) $this->getAttribute('id'), $basePath);
    }

    /**
     * Get thumbnail path with videoId placeholder replaced.
     */
    public function getThumbnailPath(): string
    {
        $basePath = config('video.storage.thumbnails_path');
        return \Str::replace('{videoId}', (string) $this->getAttribute('id'), $basePath);
    }

    /**
     * Get sprite path with videoId placeholder replaced.
     */
    public function getSpritePath(): string
    {
        $basePath = config('video.storage.sprites_path');
        return \Str::replace('{videoId}', (string) $this->getAttribute('id'), $basePath);
    }

    /**
     * Check if video is fully encoded.
     */
    public function isFullyEncoded(): bool
    {
        $totalProfiles = $this->profiles()->count();
        $encodedProfiles = $this->profiles()->where('encoded', true)->count();

        return $totalProfiles > 0 && $totalProfiles === $encodedProfiles;
    }

    /**
     * Get encoding progress percentage.
     */
    public function getEncodingProgress(): int
    {
        $totalProfiles = $this->profiles()->count();

        if ($totalProfiles === 0) {
            return 0;
        }

        $encodedProfiles = $this->profiles()->where('encoded', true)->count();

        return (int) round(($encodedProfiles / $totalProfiles) * 100);
    }

    /**
     * Get video status badge color.
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get human readable file size.
     */
    public function getReadableSize(): string
    {
        $bytes = $this->original_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get human readable duration.
     */
    public function getReadableDuration(): string
    {
        $seconds = $this->duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }



    /**
     * Check if video has thumbnail.
     */
    public function hasThumbnail(): bool
    {
        return !empty($this->thumbnail_path) && Storage::disk(config('video.storage.disk'))->exists($this->thumbnail_path);
    }

    /**
     * Check if video has sprite sheet.
     */
    public function hasSprite(): bool
    {
        return !empty($this->scrubbing_sprite_path) && Storage::disk(config('video.storage.disk'))->exists($this->scrubbing_sprite_path);
    }

    /**
     * Get sprite metadata for player.
     */
    public function getSpriteMetadata(): array
    {
        if (!$this->hasSprite()) {
            return [];
        }

        return [
            'url' => $this->getScrubbiingSpriteUrl(),
            'columns' => $this->sprite_columns,
            'rows' => $this->sprite_rows,
            'interval' => $this->sprite_interval,
            'total_frames' => $this->sprite_columns * $this->sprite_rows,
        ];
    }

    /**
     * Get HLS manifest URL.
     */
    public function getHlsManifestUrl(): ?string
    {
        $path = $this->getAttribute('hls_manifest_path');
        if (!$path) return null;

        $disk = config('video.storage.disk');
        return Storage::disk($disk)->url($path);
    }

    /**
     * Get DASH manifest URL.
     */
    public function getDashManifestUrl(): ?string
    {
        $path = $this->getAttribute('dash_manifest_path');
        if (!$path) return null;

        $disk = config('video.storage.disk');
        return Storage::disk($disk)->url($path);
    }

    /**
     * Get available profiles with fallback.
     */
    public function getAvailableProfiles(): array
    {
        return $this->getAttribute('abr_profiles') ?? [];
    }

    /**
     * Get video URL for specific profile with fallback.
     */
        public function getUrl(?string $profile = null): ?string
    {
        $availableProfiles = $this->getAvailableProfiles();

        if ($profile && isset($availableProfiles[$profile])) {
            $path = $availableProfiles[$profile];
        } else {
            // Get default profile (highest quality available)
            $videoProfile = $this->profiles()->where('encoded', true)->orderBy('width', 'desc')->first();
            if (!$videoProfile) return null;
            $path = $videoProfile->generateProfilePath();
        }

        if (!$path) return null;

        $disk = config('video.storage.disk');
        return Storage::disk($disk)->url($path);
    }

    /**
     * Check if video supports ABR streaming.
     */
    public function supportsAbr(): bool
    {
        $profiles = $this->getAvailableProfiles();
        return count($profiles) > 1;
    }

    /**
     * Get video metadata for player.
     */
    public function getPlayerMetadata(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'title' => $this->getAttribute('title'),
            'duration' => $this->getAttribute('duration'),
            'thumbnail' => $this->getThumbnailUrl(),
            'sprite' => $this->getSpriteMetadata(),
            'hls' => $this->getHlsManifestUrl(),
            'dash' => $this->getDashManifestUrl(),
            'profiles' => $this->getAvailableProfiles(),
            'supportsAbr' => $this->supportsAbr(),
        ];
    }
}
