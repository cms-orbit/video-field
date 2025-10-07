<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Fields\VideoField;

use CmsOrbit\VideoField\Entities\Video\Video;
use Illuminate\Support\Arr;
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
        'size' => 0, // Will be set in constructor from config
        'uploadUrl' => '/settings/systems/files',
        'sortUrl' => '/settings/systems/files/sort',
        'errorSize' => 'File ":name" is too large to upload (max :sizeMB)',
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
        'size',
        'uploadUrl',
        'sortUrl',
        'errorSize',
        'errorType'
    ];

    public function __construct()
    {
        $this->attributes['storage'] = config('orbit-video.storage.disk');
        $this->attributes['path'] = sprintf('orbit-video-original/%s', date('Y/m/d'));
        $this->attributes['size'] = (int) ceil(config('orbit-video.upload.max_file_size') / 1024 / 1024); // Convert bytes to MB

        // Load attachment relations if applicable
        $this->addBeforeRender(fn () => $this->setValue());
    }

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
        return null;
    }

    /**
     * Get the field's value for display.
     */
    public function setValue(): void
    {
        $videoData = $this->get('value');
        $videoId = Arr::get($videoData, 'id');
        if (!is_array($videoData) || !$videoId) return;

        $videoModel = Video::query()->find($videoId);
        if (!$videoModel) return;

        $bestSize = (int) Arr::get($videoData, 'profiles.best.file_size', 0);

        $this->set('value', json_encode([
            'type' => 'existing',
            'video_id' => $videoId,
            'video' => [
                'id' => $videoId,
                'title' => $videoData['title'] ?? null,
                'filename' => $videoModel->originalFile?->getAttribute('original_name'),
                'duration' => $videoData['duration'] ?? null,
                'file_size' => $bestSize,
                'status' => $videoData['status'] ?? null,
                'thumbnail_url' => $videoModel->getThumbnailUrl(),
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
