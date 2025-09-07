<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Screen\Actions\ModalToggle;

class EncodingStatusLayout extends Table
{
    /**
     * Data source.
     *
     * @var string
     */
    protected $target = 'video.encoding_logs';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('profile', __('Profile'))
                ->render(function ($log) {
                    return $log->videoProfile->getAttribute('profile');
                }),

            TD::make('status', __('Status'))
                ->render(function ($log) {
                    $status = $log->getAttribute('status');
                    $color = $log->getStatusColor();

                    return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-' . $color . '-100 text-' . $color . '-800">' . ucfirst($status) . '</span>';
                }),

            TD::make('progress', __('Progress'))
                ->render(function ($log) {
                    $progress = $log->getAttribute('progress');
                    if ($progress === null) return 'N/A';

                    return '<div class="progress" style="width: 100px;">
                        <div class="progress-bar" role="progressbar" style="width: ' . $progress . '%"
                             aria-valuenow="' . $progress . '" aria-valuemin="0" aria-valuemax="100">
                            ' . $progress . '%
                        </div>
                    </div>';
                }),

            TD::make('message', __('Message'))
                ->render(function ($log) {
                    $message = $log->getAttribute('message');
                    if (!$message) return 'N/A';

                    return '<span class="text-truncate d-inline-block" style="max-width: 200px;"
                             title="' . e($message) . '">' . e($message) . '</span>';
                }),

            TD::make('processing_time', __('Processing Time'))
                ->render(function ($log) {
                    return $log->getReadableProcessingTime();
                }),

            TD::make('created_at', __('Started At'))
                ->render(function ($log) {
                    return $log->getAttribute('created_at')?->format('Y-m-d H:i:s');
                }),

            TD::make('ffmpeg_command', __('FFmpeg Command'))
                ->render(function ($log) {
                    $command = $log->getAttribute('ffmpeg_command');
                    if (!$command) return 'N/A';

                    return ModalToggle::make(__('View'))
                        ->icon('bs.terminal')
                        ->modal('ffmpeg-command-modal')
                        ->method('viewFfmpegCommand')
                        ->parameters(['log_id' => $log->getAttribute('id')])
                        ->class('btn btn-sm btn-outline-secondary');
                }),

            TD::make('error_output', __('Error Output'))
                ->render(function ($log) {
                    $error = $log->getAttribute('error_output');
                    if (!$error) return 'N/A';

                    return ModalToggle::make(__('View'))
                        ->icon('bs.exclamation-triangle')
                        ->modal('error-output-modal')
                        ->method('viewErrorOutput')
                        ->parameters(['log_id' => $log->getAttribute('id')])
                        ->class('btn btn-sm btn-outline-danger');
                }),
        ];
    }
}
