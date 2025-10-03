<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use CmsOrbit\VideoField\Entities\Video\Video;
use Illuminate\Support\Facades\Log;

trait HasVideos
{
    /**
     * Boot the trait and add model events.
     */
    protected static function bootHasVideos(): void
    {
        static::saved(function ($model) {
            $model->handleVideoFieldsAfterSave();
        });
    }

    /**
     * Handle video fields after model is saved.
     */
    protected function handleVideoFieldsAfterSave(): void
    {
        if (!request()->has('video_fields_processed')) {
            return;
        }

        foreach ($this->getVideoFields() as $fieldName) {
            $videoData = request()->input($fieldName);
            
            if ($videoData) {
                $videoData = json_decode($videoData, true);
                
                if (isset($videoData['video_id'])) {
                    $video = Video::find($videoData['video_id']);
                    if ($video) {
                        $this->replaceVideo($fieldName, $video);
                    }
                }
            } else {
                $this->detachVideo($fieldName);
            }
        }
    }

    /**
     * Get video profiles for this model.
     * Override this method in your model to customize profiles.
     */
    protected function getVideoProfiles(): array
    {
        return config('orbit-video.default_profiles', []);
    }

    /**
     * Get encoding configuration for this model.
     * Override this method in your model to customize encoding settings.
     */
    protected function getVideoEncodingConfig(): array
    {
        return config('orbit-video.default_encoding', []);
    }

    /**
     * Get the video fields defined for this model.
     * Override this property in your model to define which fields are video fields.
     */
    protected function getVideoFields(): array
    {
        return $this->videoFields ?? [];
    }

    /**
     * Define the relationship between this model and videos.
     */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_field_relations')
            ->withPivot(['field_name', 'sort_order', 'model_type', 'model_id'])
            ->orderByPivot('sort_order');
    }

    /**
     * Get video for a specific field.
     */
    public function getVideo(string $field): ?Video
    {
        $relation = \DB::table('video_field_relations')
            ->where('model_type', static::class)
            ->where('model_id', $this->getAttribute('id'))
            ->where('field_name', $field)
            ->first();

        if (!$relation) {
            return null;
        }

        return Video::find($relation->video_id);
    }

    /**
     * Get videos for multiple fields.
     */
    public function getVideos(array $fields = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = \DB::table('video_field_relations')
            ->where('model_type', static::class)
            ->where('model_id', $this->getAttribute('id'));

        if (!empty($fields)) {
            $query->whereIn('field_name', $fields);
        } elseif (!empty($this->getVideoFields())) {
            $query->whereIn('field_name', $this->getVideoFields());
        }

        $relations = $query->get();
        $videoIds = $relations->pluck('video_id')->toArray();

        if (empty($videoIds)) {
            return collect();
        }

        return Video::whereIn('id', $videoIds)->get();
    }

    /**
     * Get streaming URL for a specific field and profile.
     */
    public function getVideoUrl(string $field, string $profile = 'FHD@30fps'): ?string
    {
        $video = $this->getVideo($field);
        return $video?->getStreamingUrl($profile);
    }

    /**
     * Get thumbnail URL for a specific field.
     */
    public function getVideoThumbnail(string $field): ?string
    {
        $video = $this->getVideo($field);
        return $video?->getThumbnailUrl();
    }

    /**
     * Get video sprite URL for scrubbing functionality.
     */
    public function getVideoSprite(string $field): ?string
    {
        $video = $this->getVideo($field);
        return $video?->getScrubbingSpriteUrl();
    }

    /**
     * Check if a specific field has a video.
     */
    public function hasVideo(string $field): bool
    {
        return $this->getVideo($field) !== null;
    }

    /**
     * Check if video is fully encoded for a specific field.
     */
    public function isVideoEncoded(string $field): bool
    {
        $video = $this->getVideo($field);
        return $video?->isFullyEncoded() ?? false;
    }

    /**
     * Get encoding progress for a specific field.
     */
    public function getVideoEncodingProgress(string $field): int
    {
        $video = $this->getVideo($field);
        return $video?->getEncodingProgress() ?? 0;
    }

    /**
     * Attach video to a specific field.
     */
    public function attachVideo(string $field, Video $video, int $sortOrder = 0): void
    {
        \DB::table('video_field_relations')->insert([
            'video_id' => $video->getAttribute('id'),
            'model_type' => static::class,
            'model_id' => $this->getAttribute('id'),
            'field_name' => $field,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Detach video from a specific field.
     */
    public function detachVideo(string $field): void
    {
        \DB::table('video_field_relations')
            ->where('model_type', static::class)
            ->where('model_id', $this->getAttribute('id'))
            ->where('field_name', $field)
            ->delete();
    }

    /**
     * Replace video for a specific field.
     */
    public function replaceVideo(string $field, Video $video, int $sortOrder = 0): void
    {
        // 기존 관계 삭제
        $this->detachVideo($field);
        
        // 새 관계 추가
        $this->attachVideo($field, $video, $sortOrder);
    }

    /**
     * Get all available video profiles with their configurations.
     */
    public function getAvailableVideoProfiles(): array
    {
        return $this->getVideoProfiles();
    }

    /**
     * Get encoding configuration for video processing.
     */
    public function getVideoEncodingSettings(): array
    {
        return $this->getVideoEncodingConfig();
    }

    /**
     * Get video metadata for a specific field.
     */
    public function getVideoMetadata(string $field): array
    {
        $video = $this->getVideo($field);

        if (!$video) {
            return [];
        }

        return [
            'id' => $video->getAttribute('id'),
            'uuid' => $video->getAttribute('uuid'),
            'title' => $video->getAttribute('title'),
            'description' => $video->getAttribute('description'),
            'duration' => $video->getAttribute('duration'),
            'readable_duration' => $video->getReadableDuration(),
            'file_size' => $video->getAttribute('original_size'),
            'readable_size' => $video->getReadableSize(),
            'status' => $video->getAttribute('status'),
            'encoding_progress' => $video->getEncodingProgress(),
            'thumbnail_url' => $video->getThumbnailUrl(),
            'sprite_url' => $video->getScrubbingSpriteUrl(),
            'profiles' => $video->profiles->map(function ($profile) {
                return [
                    'profile' => $profile->getAttribute('profile'),
                    'url' => $profile->getUrl(),
                    'resolution' => $profile->getResolution(),
                    'quality_label' => $profile->getQualityLabel(),
                    'file_size' => $profile->getAttribute('file_size'),
                    'readable_size' => $profile->getReadableSize(),
                    'encoded' => $profile->getAttribute('encoded'),
                ];
            })->toArray(),
        ];
    }
}
