<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts\Modals;

use App\Settings\Extends\OrbitLayout;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Screen\Fields\Badge;

class ProfileLogsModal extends Table
{
    /**
     * Data source.
     *
     * @var string
     */
    protected $target = 'profileLogsModal.logs';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('status', __('Status'))
                ->render(function ($log) {
                    $status = $log->getAttribute('status');
                    $color = $log->getStatusColor();

                    return Badge::make(ucfirst($status))
                        ->type($color);
                }),

            TD::make('progress', __('Progress'))
                ->render(function ($log) {
                    $progress = $log->getAttribute('progress');
                    if ($progress === null) return 'N/A';

                    return $progress . '%';
                }),

            TD::make('message', __('Message'))
                ->render(function ($log) {
                    $message = $log->getAttribute('message');
                    if (!$message) return 'N/A';

                    return '<span class="text-truncate d-inline-block" style="max-width: 300px;"
                             title="' . e($message) . '">' . e($message) . '</span>';
                }),

            TD::make('processing_time', __('Processing Time'))
                ->render(function ($log) {
                    return $log->getReadableProcessingTime();
                }),

            TD::make('created_at', __('Created At'))
                ->render(function ($log) {
                    return $log->getAttribute('created_at')?->format('Y-m-d H:i:s');
                }),
        ];
    }
}
