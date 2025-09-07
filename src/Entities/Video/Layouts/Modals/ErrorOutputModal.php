<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts\Modals;

use App\Settings\Extends\OrbitLayout;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Fields\Code;

class ErrorOutputModal extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Code::make('errorOutputModal.error')
                ->title(__('Error Output'))
                ->language('text')
                ->readonly()
                ->height('400px')
                ->help(__('Error output from FFmpeg encoding process')),
        ];
    }
}
