<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use CmsOrbit\VideoField\Entities\Video\Video;
use Illuminate\Support\Facades\Auth;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class VideoListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'videos';

    /**
     * Get the table columns.
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->sort()
                ->filter(TD::FILTER_TEXT)
                ->width('60px'),

            TD::make('thumbnail', __('Thumbnail'))
                ->width('80px')
                ->render(function (Video $video) {
                    if ($video->hasThumbnail()) {
                        $thumbnailUrl = $video->getThumbnailUrl();
                        return "<img src='{$thumbnailUrl}' class='img-thumbnail' style='width: 60px; height: 40px; object-fit: cover;'>";
                    }
                    return "<div class='bg-light d-flex align-items-center justify-content-center' style='width: 60px; height: 40px;'><i class='bs bs-play-circle text-muted'></i></div>";
                }),

            TD::make('title', __('Title'))
                ->sort()
                ->filter(TD::FILTER_TEXT)
                ->render(function (Video $video) {
                    $originalFile = $video->originalFile;
                    if (!$originalFile) {
                        return '<span class="text-muted">' . __('No file') . '</span>';
                    }

                    $filename = $originalFile->getAttribute('original_name') ?? $originalFile->getAttribute('name');
                    $size = $this->formatFileSize($originalFile->getAttribute('size'));
                    $mimeType = $originalFile->getAttribute('mime');

                    return Link::make($video->getAttribute('title'))
                            ->route('settings.entities.videos.edit', $video) .
                        "<br /><small class='text-muted'>{$filename} • {$size} • {$mimeType}</small>";
                }),

            TD::make('video_info', __('Video Info'))
                ->render(function (Video $video) {
                    $duration = $video->getReadableDuration();
                    $resolution = $video->getAttribute('original_width') && $video->getAttribute('original_height')
                        ? $video->getAttribute('original_width') . '×' . $video->getAttribute('original_height')
                        : 'Unknown';
                    $framerate = $video->getAttribute('original_framerate')
                        ? round($video->getAttribute('original_framerate'), 2) . ' fps'
                        : 'Unknown';
                    $bitrate = $video->getAttribute('original_bitrate')
                        ? $this->formatBitrate($video->getAttribute('original_bitrate'))
                        : 'Unknown';

                    return "<div>
                        <strong>{$duration}</strong><br>
                        <small class='text-muted'>{$resolution} • {$framerate}</small><br>
                        <small class='text-muted'>{$bitrate}</small>
                    </div>";
                }),

            TD::make('status', __('Status'))
                ->width('120px')
                ->render(function (Video $video) {
                    $color = $video->getStatusColor();
                    $status = $video->getAttribute('status');
                    $progress = $video->getEncodingProgress();

                    $statusText = match($status) {
                        'uploading' => __('Uploading'),
                        'upload_failed' => __('Upload Failed'),
                        'uploaded' => __('Uploaded'),
                        'pending' => __('Pending'),
                        'processing' => __('Processing'),
                        'completed' => __('Completed'),
                        'failed' => __('Failed'),
                        default => ucfirst($status),
                    };

                    $badge = "<span class=\"badge bg-{$color}\">" . e($statusText) . "</span>";

                    if (in_array($status, ['processing', 'uploading']) && $progress > 0) {
                        $badge .= "<br><div class='progress mt-1' style='height: 4px;'>
                            <div class='progress-bar' role='progressbar' style='width: {$progress}%' aria-valuenow='{$progress}' aria-valuemin='0' aria-valuemax='100'></div>
                        </div>";
                    }

                    return $badge;
                }),

            TD::make('profiles', __('Profiles'))
                ->width('100px')
                ->render(function (Video $video) {
                    $total = $video->profiles()->count();
                    $encoded = $video->profiles()->where('encoded', true)->count();

                    if ($total === 0) {
                        return '<span class="text-muted">' . __('No profiles') . '</span>';
                    }

                    $profiles = $video->profiles()
                        ->where('encoded', true)
                        ->orderBy('width', 'desc')
                        ->get()
                        ->map(function ($profile) {
                            return $profile->getAttribute('width') . 'p';
                        })
                        ->take(3)
                        ->implode(', ');

                    $more = $encoded > 3 ? ' +' . ($encoded - 3) : '';

                    return "<div>
                        <strong>{$encoded}/{$total}</strong><br>
                        <small class='text-muted'>{$profiles}{$more}</small>
                    </div>";
                }),

            TD::make('storage_info', __('Storage'))
                ->render(function (Video $video) {
                    $hls = $video->getAttribute('hls_manifest_path') ? 'HLS' : '';
                    $dash = $video->getAttribute('dash_manifest_path') ? 'DASH' : '';
                    $sprite = $video->hasSprite() ? 'Sprite' : '';
                    $thumbnail = $video->hasThumbnail() ? 'Thumb' : '';

                    $features = array_filter([$hls, $dash, $sprite, $thumbnail]);
                    $featuresText = implode(', ', $features);

                    return "<div>
                        <small class='text-muted'>{$featuresText}</small>
                    </div>";
                }),

            TD::make('created_at', __('Created'))
                ->align(TD::ALIGN_RIGHT)
                ->sort()
                ->width('120px')
                ->render(function (Video $video) {
                    return $video->getAttribute('created_at')?->format('Y-m-d H:i');
                }),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function (Video $video) {
                    $actions = $this->getActions($video);
                    if (count($actions) < 1) return null;
                    return DropDown::make()->icon('bs.three-dots')->list($actions);
                }),
        ];
    }

    private function getActions(Video $video): array
    {
        $user = Auth::user();
        $actions = [];

        if ($user && $user->hasAccess('settings.entities.videos.edit')) {
            $actions[] = Link::make(__('Edit'))
                ->route('settings.entities.videos.edit', $video->getKey())
                ->icon('bs.pencil');
        }

        if ($user && $user->hasAccess('settings.entities.videos.delete')) {
            $actions[] = Button::make(__('Delete'))
                ->confirm(__('Are you sure you want to delete this video?'))
                ->method('remove', ['id' => $video->getKey()])
                ->icon('bs.trash3');
        }
        return $actions;
    }

    /**
     * Format file size in human readable format.
     */
    private function formatFileSize(?int $bytes): string
    {
        if (!$bytes) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format bitrate in human readable format.
     */
    private function formatBitrate(?int $bitrate): string
    {
        if (!$bitrate) {
            return 'Unknown';
        }

        if ($bitrate >= 1000000) {
            return round($bitrate / 1000000, 1) . ' Mbps';
        } elseif ($bitrate >= 1000) {
            return round($bitrate / 1000, 1) . ' Kbps';
        }

        return $bitrate . ' bps';
    }
}
