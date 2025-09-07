<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\DateTimer;
use App\Settings\Extends\OrbitLayout;

class BasicInformationLayout
{
    /**
     * Get the layout elements to be displayed.
     *
     * @return array
     */
    public function __invoke(): array
    {
        return [
            // Video Information
            OrbitLayout::rows([
                Input::make('video.title')
                    ->title(__('Title'))
                    ->required()
                    ->maxlength(255)
                    ->help(__('Enter the video title')),

                TextArea::make('video.description')
                    ->title(__('Description'))
                    ->rows(3)
                    ->help(__('Enter a description for the video')),

                Group::make([
                    Input::make('video.duration')
                        ->title(__('Duration'))
                        ->readonly()
                        ->help(__('Video duration in seconds')),

                    Input::make('video.original_size')
                        ->title(__('File Size'))
                        ->readonly()
                        ->help(__('Original file size in bytes')),
                ]),

                Group::make([
                    Input::make('video.original_width')
                        ->title(__('Width'))
                        ->readonly()
                        ->help(__('Original video width')),

                    Input::make('video.original_height')
                        ->title(__('Height'))
                        ->readonly()
                        ->help(__('Original video height')),
                ]),

                Group::make([
                    Input::make('video.original_framerate')
                        ->title(__('Frame Rate'))
                        ->readonly()
                        ->help(__('Original video frame rate')),

                    Input::make('video.original_bitrate')
                        ->title(__('Bitrate'))
                        ->readonly()
                        ->help(__('Original video bitrate')),
                ]),

                Group::make([
                    DateTimer::make('video.created_at')
                        ->title(__('Created At'))
                        ->readonly()
                        ->format('Y-m-d H:i:s'),

                    DateTimer::make('video.updated_at')
                        ->title(__('Updated At'))
                        ->readonly()
                        ->format('Y-m-d H:i:s'),
                ]),
            ]),
        ];
    }
}
