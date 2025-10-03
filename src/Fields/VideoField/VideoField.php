<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Fields\VideoField;

use Orchid\Screen\Field;
use Orchid\Screen\Repository;

class VideoField extends Field
{
    /**
     * Blade template.
     */
    protected $view = 'cms-orbit-video-field::video_field';

    /**
     * Default attributes value.
     */
    protected $attributes = [
        'withoutUpload' => false,
        'withoutExists' => false,
        'placeholder' => 'Search videos...',
        'maxResults' => 10,
        'ajaxUrl' => '',
        'recentUrl' => '',
        'group' => 'video',
        'storage' => 'public',
        'path' => '',
        'count' => 1,
        'size' => 500,
        'uploadUrl' => '/settings/systems/files',
        'sortUrl' => '/settings/systems/files/sort',
        'errorSize' => 'File ":name" is too large to upload (max 500MB)',
        'errorType' => 'The attached file must be a video'
    ];

    /**
     * Attributes available for a particular tag.
     */
    protected $inlineAttributes = [
        'name',
        'value',
        'withoutUpload',
        'withoutExists',
        'placeholder',
        'maxResults',
        'ajaxUrl',
        'recentUrl',
        'group',
        'storage',
        'path',
        'count',
        'size',
        'uploadUrl',
        'sortUrl',
        'errorSize',
        'errorType'
    ];

    /**
     * Disable upload functionality.
     */
    public function withoutUpload(): self
    {
        $this->attributes['withoutUpload'] = true;

        return $this;
    }

    /**
     * Disable existing video selection.
     */
    public function withoutExists(): self
    {
        $this->attributes['withoutExists'] = true;

        return $this;
    }

    /**
     * Set placeholder text.
     */
    public function placeholder(string $placeholder): self
    {
        $this->attributes['placeholder'] = $placeholder;

        return $this;
    }

    /**
     * Set maximum search results.
     */
    public function maxResults(int $maxResults): self
    {
        $this->attributes['maxResults'] = $maxResults;

        return $this;
    }

    /**
     * Set AJAX search URL.
     */
    public function ajaxUrl(string $url): self
    {
        $this->attributes['ajaxUrl'] = $url;

        return $this;
    }

    /**
     * Set recent videos URL.
     */
    public function recentUrl(string $url): self
    {
        $this->attributes['recentUrl'] = $url;

        return $this;
    }

    /**
     * Set group for attachments.
     */
    public function group(string $group): self
    {
        $this->attributes['group'] = $group;

        return $this;
    }

    /**
     * Set storage disk.
     */
    public function storage(string $storage): self
    {
        $this->attributes['storage'] = $storage;

        return $this;
    }

    /**
     * Set upload path.
     */
    public function path(string $path): self
    {
        $this->attributes['path'] = $path;

        return $this;
    }

    /**
     * Set maximum file count.
     */
    public function count(int $count): self
    {
        $this->attributes['count'] = $count;

        return $this;
    }

    /**
     * Set maximum file size in MB.
     */
    public function size(int $size): self
    {
        $this->attributes['size'] = $size;

        return $this;
    }

    /**
     * Set upload URL.
     */
    public function uploadUrl(string $url): self
    {
        $this->attributes['uploadUrl'] = $url;

        return $this;
    }

    /**
     * Set sort URL.
     */
    public function sortUrl(string $url): self
    {
        $this->attributes['sortUrl'] = $url;

        return $this;
    }

    /**
     * Set error message for file size.
     */
    public function errorSize(string $message): self
    {
        $this->attributes['errorSize'] = $message;

        return $this;
    }

    /**
     * Set error message for file type.
     */
    public function errorType(string $message): self
    {
        $this->attributes['errorType'] = $message;

        return $this;
    }


    /**
     * Handle the field's value when saving.
     */
    public function modify(Repository $repository, string $key, $value)
    {
        // 비디오 데이터를 처리하는 로직은 HasVideos 트레이트에서 처리
        // 여기서는 단순히 값만 반환
        return $value;
    }

    /**
     * Get the field's value for display.
     */
    public function getValue(Repository $repository, string $key)
    {
        $model = $repository->get('document');

        if (!$model || !method_exists($model, 'getVideo')) {
            return null;
        }

        $video = $model->getVideo($key);
        if ($video && !$video->relationLoaded('originalFile')) {
            $video->load('originalFile');
        }

        if (!$video) {
            return null;
        }

        return [
            'type' => 'existing',
            'video_id' => $video->getAttribute('id'),
            'video' => [
                'id' => $video->getAttribute('id'),
                'title' => $video->getAttribute('title'),
                'filename' => $video->originalFile?->getAttribute('original_name') ?? $video->getAttribute('title'),
                'duration' => $video->getAttribute('duration'),
                'file_size' => $video->originalFile?->getAttribute('size') ?? 0,
                'status' => $video->getAttribute('status'),
                'thumbnail_url' => $video->getThumbnailUrl(),
            ]
        ];
    }
}
