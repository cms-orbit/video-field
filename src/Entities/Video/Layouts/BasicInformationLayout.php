<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class BasicInformationLayout extends Rows
{
    /**
     * Get the layout elements to be displayed.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            Input::make('video.title')
                ->title(__('Title'))
                ->required()
                ->maxlength(255)
                ->help(__('Enter the video title')),

            TextArea::make('video.description')
                ->title(__('Description'))
                ->rows(3)
                ->help(__('Enter a description for the video')),
        ];
    }
}
