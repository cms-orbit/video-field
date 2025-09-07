<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts\Modals;

use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Fields\Code;

class ProfileLogsModal extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Code::make('profileLogsModal.logs')
                ->title(__('Encoding Logs'))
                ->readonly()
                ->height('400px')
                ->help(__('Detailed encoding logs for this profile')),
        ];
    }
}
