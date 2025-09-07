<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use App\Settings\Extends\OrbitLayout;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Picture;
use Orchid\Screen\Fields\Code;

class VideoDetailsLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Group::make([
                Input::make('video.status')
                    ->title(__('Status'))
                    ->readonly()
                    ->help(__('Current processing status')),

                Input::make('video.encoding_progress')
                    ->title(__('Encoding Progress'))
                    ->readonly()
                    ->help(__('Overall encoding progress percentage')),
            ]),

            Group::make([
                Input::make('video.thumbnail_path')
                    ->title(__('Thumbnail Path'))
                    ->readonly()
                    ->help(__('Path to video thumbnail')),

                CheckBox::make('video.has_thumbnail')
                    ->title(__('Has Thumbnail'))
                    ->readonly()
                    ->help(__('Whether thumbnail exists')),
            ]),

            Group::make([
                Input::make('video.scrubbing_sprite_path')
                    ->title(__('Sprite Path'))
                    ->readonly()
                    ->help(__('Path to scrubbing sprite sheet')),

                CheckBox::make('video.has_sprite')
                    ->title(__('Has Sprite'))
                    ->readonly()
                    ->help(__('Whether sprite sheet exists')),
            ]),

            Group::make([
                Input::make('video.hls_manifest_path')
                    ->title(__('HLS Manifest Path'))
                    ->readonly()
                    ->help(__('Path to HLS manifest file')),

                Input::make('video.dash_manifest_path')
                    ->title(__('DASH Manifest Path'))
                    ->readonly()
                    ->help(__('Path to DASH manifest file')),
            ]),

            Code::make('video.meta_data')
                ->title(__('Metadata'))
                ->language('json')
                ->readonly()
                ->height('200px')
                ->help(__('Video metadata in JSON format')),
        ];
    }
}
