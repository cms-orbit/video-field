<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Input;

class VideoProfilesAndEncodingLayout extends Table
{
    /**
     * Data source.
     *
     * @var string
     */
    protected $target = 'video.profiles';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('profile', __('Profile'))
                ->sort()
                ->filter(Input::make())
                ->render(function ($profile) {
                    $profileName = $profile->getAttribute('profile');

                    // Get technical details
                    $width = $profile->getAttribute('width');
                    $height = $profile->getAttribute('height');
                    $framerate = $profile->getAttribute('framerate');
                    $bitrate = $profile->getAttribute('bitrate');

                    // Build details string
                    $details = [];
                    if ($width && $height) {
                        $details[] = $width . ' × ' . $height;
                    }
                    if ($framerate) {
                        $details[] = $framerate . 'fps';
                    }
                    if ($bitrate) {
                        $details[] = $bitrate . 'Mbps';
                    }

                    $detailsString = implode(' • ', $details);

                    return '<div>
                        <div class="font-medium">' . e($profileName) . '</div>
                        ' . ($detailsString ? '<small class="text-muted">' . e($detailsString) . '</small>' : '') . '
                    </div>';
                }),

            TD::make('status', __('Status'))
                ->render(function ($profile) {
                    $encoded = $profile->getAttribute('encoded');
                    $status = $profile->getAttribute('status');

                    if ($encoded) {
                        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">' . __('Completed') . '</span>';
                    } elseif ($status === 'encoding') {
                        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">' . __('Encoding') . '</span>';
                    } else {
                        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . __('Pending') . '</span>';
                    }
                }),


            TD::make('created_at', __('Created'))
                ->render(function ($profile) {
                    $createdAt = $profile->getAttribute('created_at');
                    if (!$createdAt) return 'N/A';

                    return '<span class="text-xs text-gray-500">' . $createdAt->format('m/d H:i') . '</span>';
                }),

            TD::make('actions', __('Actions'))
                ->render(function ($profile) {
                    $buttons = [];

                    // View Logs button
                    $buttons[] = ModalToggle::make(__('Logs'))
                        ->icon('bs.journal-text')
                        ->modal('profile-logs-modal', [
                            'profile_id' => $profile->getAttribute('id')
                        ])
                        ->class('btn btn-xs btn-outline-info me-1');

                    // FFmpeg Command button (if exists)
                    $latestLog = $profile->encodingLogs()->latest()->first();
                    if ($latestLog && $latestLog->getAttribute('ffmpeg_command')) {
                        $buttons[] = ModalToggle::make(__('FFmpeg'))
                            ->icon('bs.terminal')
                            ->modal('ffmpeg-command-modal', [
                                'log_id' => $latestLog->getAttribute('id')
                            ])
                            ->class('btn btn-xs btn-outline-secondary me-1');
                    }

                    // Error Output button (if exists)
                    if ($latestLog && $latestLog->getAttribute('error_output')) {
                        $buttons[] = ModalToggle::make(__('Error'))
                            ->icon('bs.exclamation-triangle')
                            ->modal('error-output-modal', [
                                'log_id' => $latestLog->getAttribute('id')
                            ])
                            ->class('btn btn-xs btn-outline-danger');
                    }

                    return implode('', $buttons);
                }),
        ];
    }
}
