<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Traits;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoFieldRelation;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

trait HasVideos
{
    /**
     * Boot the trait and add model events.
     */
    protected static function bootHasVideos(): void
    {
        static $videoColumns = [];

        static::addGlobalScope('withVideoFields', function (Builder $builder) {
            $builder->with(['videos.profiles']);
        });

        static::retrieved(function (self $model) {
            $model->mapVideoFieldsToAttributes();
        });

        static::saving(function (self $model) use (&$videoColumns){
            $videoFields = $model->getVideoFields();
            foreach ($videoFields as $field) {
                $videoColumns[$field] = $model->{$field};
                unset($model->attributes[$field]);
            }
        });

        static::saved(function (self $model) use (&$videoColumns) {
            $model->videos()->detach();
            foreach ($videoColumns as $fieldName => $videoData) {
                $videoId = null;
                if ($videoData) {
                    $data = json_decode($videoData, true);
                    $videoId = $data['video_id'] ?? null;
                }

                if ($videoId) {
                    VideoFieldRelation::query()->updateOrCreate(
                        [
                            'field_name' => $fieldName,
                            'model_type' => static::class,
                            'model_id' => $model->getKey()
                        ],
                        [
                            'video_id' => $videoId,
                        ]
                    );
                }
            }
            $videoColumns = [];
        });
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
    public function videos(): MorphToMany
    {
        // 설정 파일에서 피벗 모델 클래스 이름을 가져옵니다.
        $pivotModelClass = config('orbit-video.video_field_relation_model');

        return $this->morphToMany(
            Video::class,
            'model',
            'video_field_relations',
            'model_id',
            'video_id'
        )
            ->withPivot(['field_name', 'sort_order'])
            ->using($pivotModelClass)
            ->orderByPivot('sort_order');
    }


    protected function mapVideoFieldsToAttributes(): void
    {
        if (!property_exists($this, 'videoFields')) return;

        foreach ($this->getVideoFields() as $fieldName) {
            $video = $this->videos
                ->firstWhere('pivot.field_name', $fieldName);

            if ($video) {
                $videoData = [
                    // 공통된 비디오 정보
                    'id' => $video->getAttribute('id'),
                    'uuid' => $video->getAttribute('uuid'),
                    'title' => $video->getAttribute('title'),
                    'description' => $video->getAttribute('description'),
                    'duration' => $video->getAttribute('duration'),
                    'status' => $video->getAttribute('status'),
                    'thumbnail_path' => $video->getAttribute('thumbnail_path'),
                    'scrubbing_sprite_path' => $video->getAttribute('scrubbing_sprite_path'),
                    'abr' => [
                        'hls' => $video->getAttribute('hls_manifest_path'),
                        'dash' => $video->getAttribute('dash_manifest_path')
                    ],
                    'original_file' => [
                        'id' => $video->getAttribute('original_file_id'),
                        'original_width' => $video->getAttribute('original_width'),
                        'original_height' => $video->getAttribute('original_height'),
                        'original_framerate' => $video->getAttribute('original_framerate'),
                        'original_bitrate' => $video->getAttribute('original_bitrate'),
                    ],
                    'profiles' => $this->mapVideoProfiles($video),
                    'created_at' => $video->getAttribute('created_at'),
                    'updated_at' => $video->getAttribute('updated_at'),
                ];

                // 모델 속성으로 직접 추가
                $this->setAttribute($fieldName, $videoData);
            } else {
                // 비디오가 없는 경우 null로 설정
                $this->setAttribute($fieldName, null);
            }
        }
    }

    protected function mapVideoProfiles(Video $video): array
    {
        $profiles = [];

        if ($video->profiles && $video->profiles->isNotEmpty()) {
            $bestProfile = null;
            $bestScore = 0;

            foreach ($video->profiles as $profile) {
                $profileName = $profile->getAttribute('profile');
                $profileData = [
                    'id' => $profile->getAttribute('id'),
                    'uuid' => $profile->getAttribute('uuid'),
                    'profile' => $profile->getAttribute('profile'),
                    'encoded' => (bool) $profile->getAttribute('encoded'),
                    'status' => $profile->getAttribute('status'),
                    'file_size' => $profile->getAttribute('file_size'),
                    'width' => $profile->getAttribute('width'),
                    'height' => $profile->getAttribute('height'),
                    'framerate' => $profile->getAttribute('framerate'),
                    'bitrate' => $profile->getAttribute('bitrate'),
                    'url' => $profile->getAttribute('path'),
                    'url_hls' => $profile->getAttribute('hls_path'),
                    'url_dash' => $profile->getAttribute('dash_path'),
                    'created_at' => $profile->getAttribute('created_at'),
                    'updated_at' => $profile->getAttribute('updated_at')
                ];

                $profiles[$profileName] = $profileData;

                // BEST 프로필 계산 (해상도 * 프레임레이트로 점수 계산)
                $score = $profile->getAttribute('width') * $profile->getAttribute('height') * $profile->getAttribute('framerate');
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestProfile = $profileName;
                }
            }

            // BEST 프로필 표기
            if ($bestProfile) {
                $bestProfileData = $profiles[$bestProfile];
                $profiles = array_merge(['best' => $bestProfileData], $profiles);
            }
        }

        return $profiles;
    }

}
