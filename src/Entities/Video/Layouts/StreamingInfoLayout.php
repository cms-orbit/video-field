<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use App\Settings\Extends\OrbitLayout;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Code;

class StreamingInfoLayout extends Rows
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
                Input::make('video.hls_manifest_url')
                    ->title(__('HLS Manifest URL'))
                    ->readonly()
                    ->help(__('URL to HLS manifest file')),

                Input::make('video.dash_manifest_url')
                    ->title(__('DASH Manifest URL'))
                    ->readonly()
                    ->help(__('URL to DASH manifest file')),
            ]),

            Input::make('video.thumbnail_url')
                ->title(__('Thumbnail URL'))
                ->readonly()
                ->help(__('URL to video thumbnail')),

            Group::make([
                CheckBox::make('video.supports_abr')
                    ->title(__('Supports ABR'))
                    ->readonly()
                    ->help(__('Whether video supports Adaptive Bitrate streaming')),

                Input::make('video.abr_profile_count')
                    ->title(__('ABR Profiles Count'))
                    ->readonly()
                    ->help(__('Number of available ABR profiles')),
            ]),

            Code::make('video.abr_profiles')
                ->title(__('ABR Profiles'))
                ->language('json')
                ->readonly()
                ->height('150px')
                ->help(__('Available ABR profiles configuration')),

            Code::make('video.player_metadata')
                ->title(__('Player Metadata'))
                ->language('json')
                ->readonly()
                ->height('200px')
                ->help(__('Complete metadata for video player integration')),
        ];
    }
}
