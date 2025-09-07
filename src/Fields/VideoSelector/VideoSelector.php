<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Fields\VideoSelector;

use Orchid\Screen\Field;
use CmsOrbit\VideoField\Entities\Video\Video;

class VideoSelector extends Field
{
    /**
     * @var string
     */
    protected $view = 'cms-orbit-video::fields.video-selector';

    /**
     * Default attributes value.
     *
     * @var array
     */
    protected $attributes = [
        'class' => 'form-control',
        'value' => null,
        'multiple' => false,
    ];

    /**
     * Attributes available for a particular tag.
     *
     * @var array
     */
    protected array $available = [
        'name',
        'value',
        'title',
        'help',
        'required',
        'disabled',
        'readonly',
        'tabindex',
        'placeholder',
        'multiple',
    ];

    /**
     * Create a new field instance.
     *
     * @param string $name
     * @param string $title
     * @param array $attributes
     */
    public function __construct(string $name, string $title = null, array $attributes = [])
    {
        parent::__construct($name, $title, $attributes);

        $this->addBeforeRender(function () {
            $this->set('videos', $this->getVideos());
        });
    }

    /**
     * Get videos for selection
     *
     * @return array
     */
    protected function getVideos(): array
    {
        return Video::select('id', 'title', 'filename')
            ->orderBy('created_at', 'desc')
            ->get()
            ->mapWithKeys(function ($video) {
                return [$video->id => $video->title . ' (' . $video->filename . ')'];
            })
            ->toArray();
    }

    /**
     * Set multiple selection
     *
     * @param bool $multiple
     * @return $this
     */
    public function multiple(bool $multiple = true): self
    {
        $this->set('multiple', $multiple);

        return $this;
    }

    /**
     * Set placeholder
     *
     * @param string $placeholder
     * @return $this
     */
    public function placeholder(string $placeholder): self
    {
        $this->set('placeholder', $placeholder);

        return $this;
    }
}
