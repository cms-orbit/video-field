<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class VideoEditLayout extends Rows
{
    /**
     * Used to create the title of a group of form elements.
     *
     * @var string|null
     */
    protected $title = 'Video Information';

    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        return [
            Input::make('video.title')
                ->title(__('Title'))
                ->placeholder(__('Enter video title'))
                ->required(),

            TextArea::make('video.description')
                ->title(__('Description'))
                ->placeholder(__('Enter video description'))
                ->rows(4),

            Input::make('video.original_filename')
                ->title(__('Original Filename'))
                ->readonly(),

            Input::make('video.original_size')
                ->title(__('File Size'))
                ->readonly()
                ->help(__('File size in bytes')),

            Input::make('video.duration')
                ->title(__('Duration'))
                ->readonly()
                ->help(__('Duration in seconds')),

            Select::make('video.status')
                ->title(__('Status'))
                ->options([
                    'pending' => __('Pending'),
                    'processing' => __('Processing'),
                    'completed' => __('Completed'),
                    'failed' => __('Failed'),
                ])
                ->required(),

            Input::make('video.mime_type')
                ->title(__('MIME Type'))
                ->readonly(),

            Input::make('video.thumbnail_path')
                ->title(__('Thumbnail Path'))
                ->readonly(),

            Input::make('video.scrubbing_sprite_path')
                ->title(__('Sprite Path'))
                ->readonly(),
        ];
    }
}
