<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Fields\VideoSelector;

use Orchid\Screen\Field;

class VideoSelectorFacade
{
    /**
     * Create a new VideoSelector field instance.
     *
     * @param string $name
     * @param string|null $title
     * @param array $attributes
     * @return VideoSelector
     */
    public static function make(string $name, string $title = null, array $attributes = []): VideoSelector
    {
        return new VideoSelector($name, $title, $attributes);
    }
}
