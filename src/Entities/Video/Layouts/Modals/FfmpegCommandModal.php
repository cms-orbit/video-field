<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts\Modals;

use App\Settings\Extends\OrbitLayout;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Fields\Code;

class FfmpegCommandModal extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            TextArea::make('ffmpegCommandModal.command')
                ->title(__('FFmpeg Command'))
                ->readonly()
                ->rows(15)
                ->help(__('The complete FFmpeg command used for encoding')),
        ];
    }
}
