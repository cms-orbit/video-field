<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use CmsOrbit\VideoField\Entities\Video\Video;
use Illuminate\Support\Facades\Auth;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
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
                ->filter(TD::FILTER_TEXT),

            TD::make('title', __('Title'))
                ->sort()
                ->filter(TD::FILTER_TEXT)
                ->render(function (Video $video) {
                    return Link::make($video->getAttribute('title'))
                        ->route('settings.entities.videos.edit', $video);
                }),

            TD::make('original_filename', __('File'))
                ->render(function (Video $video) {
                    return [
                        'filename' => $video->getAttribute('original_filename'),
                        'size' => $video->getReadableSize(),
                    ];
                }),

            TD::make('duration', __('Duration'))
                ->render(function (Video $video) {
                    return $video->getReadableDuration();
                }),

            TD::make('status', __('Status'))
                ->render(function (Video $video) {
                    $color = $video->getStatusColor();
                    $status = $video->getAttribute('status');
                    $progress = $video->getEncodingProgress();

                    $badge = "<span class=\"badge bg-{$color}\">" . ucfirst($status) . "</span>";

                    if ($status === 'processing' && $progress > 0) {
                        $badge .= " <small>({$progress}%)</small>";
                    }

                    return $badge;
                }),

            TD::make('profiles', __('Profiles'))
                ->render(function (Video $video) {
                    $total = $video->profiles()->count();
                    $encoded = $video->profiles()->where('encoded', true)->count();

                    return "{$encoded}/{$total}";
                }),

            TD::make('created_at', __('Created'))
                ->align(TD::ALIGN_RIGHT)
                ->sort()
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

        if ($user->hasAccess('settings.entities.videos.edit')) {
            $actions[] = Link::make(__('Edit'))
                ->route('settings.entities.videos.edit', $video->getKey())
                ->icon('bs.pencil');
        }

        if ($user->hasAccess('settings.entities.videos.delete')) {
            $actions[] = Button::make(__('Delete'))
                ->confirm(__('Are you sure you want to delete this video?'))
                ->method('remove', ['id' => $video->getKey()])
                ->icon('bs.trash3');
        }

        // Encoding actions
        if ($video->getAttribute('status') !== 'processing') {
            $actions[] = Button::make(__('Re-encode'))
                ->confirm(__('This will start encoding process. Continue?'))
                ->method('encode', ['id' => $video->getKey()])
                ->icon('bs.arrow-clockwise');
        }

        return $actions;
    }
}
