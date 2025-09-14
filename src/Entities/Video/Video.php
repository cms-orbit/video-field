<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use App\Services\DynamicModel;
use App\Services\Traits\HasPermissions;
use App\Services\Traits\SettingMenuItemTrait;
use CmsOrbit\VideoField\Traits\VideoStorageTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Orchid\Attachment\Models\Attachment;
use Exception;

/**
 * @property Attachment $originalFile
 */
class Video extends DynamicModel
{
    use SettingMenuItemTrait, HasPermissions, VideoStorageTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'videos';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
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

    public function originalFile(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'original_file_id');
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
     * Get the related models for this video.
     */
    public function relatedModels(): HasMany
    {
        return $this->hasMany(VideoFieldRelation::class);
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

        return $videoProfile ? Storage::disk(config('orbit-video.storage.disk'))->url($videoProfile->path) : null;
    }

    /**
     * Get thumbnail URL.
     */
    public function getThumbnailUrl(): ?string
    {
        return $this->generateStorageUrl($this->getAttribute('thumbnail_path'));
    }

    /**
     * Get scrubbing sprite URL.
     */
    public function getScrubbingSpriteUrl(): ?string
    {
        $metadata = $this->getSpriteMetadata();
        $path = $metadata['sprite']['path'] ?? null;
        return $path ? $this->generateStorageUrl($path) : null;
    }

    /**
     * Get path with videoId placeholder replaced.
     */
    public function getVideoPath(): string
    {
        if (!$this->originalFile) {
            throw new \Exception('Original file not found for video: ' . $this->getAttribute('id'));
        }
        return Storage::disk(config('orbit-video.storage.disk'))->path($this->originalFile->physicalPath());
    }

    /**
     * Get thumbnail path with videoId placeholder replaced.
     */
    public function getThumbnailPath(): string
    {
        $basePath = config('orbit-video.storage.thumbnails_path');
        return $this->replaceVideoIdInPath($basePath, $this->getAttribute('id'));
    }

    /**
     * Get sprite path with videoId placeholder replaced.
     */
    public function getSpritePath(): string
    {
        $basePath = config('orbit-video.storage.sprites_path');
        return $this->replaceVideoIdInPath($basePath, $this->getAttribute('id'));
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
        return $this->storageFileExists($this->getAttribute('thumbnail_path'));
    }

    /**
     * Check if video has sprite sheet.
     */
    public function hasSprite(): bool
    {
        return $this->storageFileExists($this->getAttribute('scrubbing_sprite_path'));
    }

    /**
     * Get sprite metadata for player.
     */
    public function getSpriteMetadata(): array
    {
        if (!$this->hasSprite()) {
            return [];
        }

        $metadataPath = $this->getAttribute('scrubbing_sprite_path');
        if (!$metadataPath) {
            return [];
        }

        try {
            $disk = config('orbit-video.storage.disk');
            $fullPath = Storage::disk($disk)->path($metadataPath);

            if (!file_exists($fullPath)) {
                return [];
            }

            $metadata = json_decode(file_get_contents($fullPath), true);
            return $metadata ?: [];
        } catch (Exception $e) {
            Log::error("Failed to load sprite metadata: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get HLS manifest URL.
     */
    public function getHlsManifestUrl(): ?string
    {
        $path = $this->getAttribute('hls_manifest_path');
        return $path ? $this->generateStorageUrl($path) : null;
    }

    /**
     * Get DASH manifest URL.
     */
    public function getDashManifestUrl(): ?string
    {
        $path = $this->getAttribute('dash_manifest_path');
        return $path ? $this->generateStorageUrl($path) : null;
    }

    /**
     * Get progressive MP4 URL.
     */
    public function getProgressiveUrl(): ?string
    {
        $exportProgressive = config('orbit-video.default_encoding.export_progressive', true);
        
        if (!$exportProgressive) {
            return null;
        }

        $profile = $this->profiles()
            ->where('encoded', true)
            ->whereNotNull('path')
            ->orderBy('width', 'desc')
            ->first();

        return $profile ? $profile->getUrl() : null;
    }

    /**
     * Regenerate manifests for this video.
     */
    public function regenerateManifests(): void
    {
        $manifestService = new \CmsOrbit\VideoField\Services\AbrManifestService();
        
        // Generate HLS manifest
        $hlsPath = $manifestService->generateHlsManifest($this);
        if ($hlsPath) {
            Log::info("HLS manifest regenerated: {$hlsPath}");
        }

        // Generate DASH manifest
        $dashPath = $manifestService->generateDashManifest($this);
        if ($dashPath) {
            Log::info("DASH manifest regenerated: {$dashPath}");
        }

        // Update ABR profiles cache
        $manifestService->updateAbrProfiles($this);
    }

    /**
     * Get the best quality HLS profile URL.
     */
    public function getBestHlsUrl(): ?string
    {
        $profile = $this->profiles()
            ->where('encoded', true)
            ->whereNotNull('hls_path')
            ->orderBy('width', 'desc')
            ->first();

        return $profile ? $profile->getHlsUrl() : null;
    }

    /**
     * Get the best quality DASH profile URL.
     */
    public function getBestDashUrl(): ?string
    {
        $profile = $this->profiles()
            ->where('encoded', true)
            ->whereNotNull('dash_path')
            ->orderBy('width', 'desc')
            ->first();

        return $profile ? $profile->getDashUrl() : null;
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
            $videoProfile = $this->profiles()
                ->where('encoded', true)
                ->whereNotNull('path')
                ->orderBy('width', 'desc')
                ->first();
            
            if (!$videoProfile) return null;
            $path = $videoProfile->getAttribute('path');
        }

        if (!$path) return null;

        $disk = config('orbit-video.storage.disk');
        return Storage::disk($disk)->url($path);
    }

    /**
     * Check if video supports ABR streaming.
     */
    public function supportsAbr(): bool
    {
        $profiles = $this->getAvailableProfiles();
        return is_array($profiles) && count($profiles) > 1;
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

    /**
     * Convert seconds to timecode format (HH:MM:SS.mmm).
     */
    public static function formatTimecode(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600.0);
        $minutes = (int) floor(fmod($seconds, 3600.0) / 60.0);
        $secs = fmod($seconds, 60.0);

        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
    }
}
