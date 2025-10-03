<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use CmsOrbit\VideoField\Entities\Video\Video;
use App\Settings\Extends\OrbitLayout;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class VideoTrashLayout extends Table
{
    /**
     * Data source.
     *
     * @var string
     */
    public $target = 'videos';

    /**
     * @return TD[]
     */
    public function columns(): iterable
    {
        return [
            TD::make('thumbnail_url', __('Thumbnail'))
                ->render(function (Video $video) {
                    $thumbnailUrl = $video->getThumbnailUrl();
                    if ($thumbnailUrl) {
                        return "<img src='{$thumbnailUrl}' class='rounded' style='width: 60px; height: 34px; object-fit: cover;' alt='Thumbnail'>";
                    }
                    return "<div class='bg-secondary rounded d-flex align-items-center justify-content-center' style='width: 60px; height: 34px;'><i class='bs-play-circle'></i></div>";
                }),

            TD::make('title', __('Title'))
                ->render(function (Video $video) {
                    return $video->getAttribute('title') ?: __('Untitled');
                }),

            TD::make('duration', __('Duration'))
                ->render(function (Video $video) {
                    return $video->getAttribute('duration') ? $video->getReadableDuration() : '-';
                }),

            TD::make('status', __('Status'))
                ->render(function (Video $video) {
                    $status = $video->getAttribute('status');
                    $color = $video->getStatusColor();
                    return "<span class='badge bg-{$color}'>" . ucfirst($status) . "</span>";
                }),

            TD::make('deleted_at', __('Deleted At'))
                ->render(function (Video $video) {
                    $deletedAt = $video->getAttribute('deleted_at');
                    if (!$deletedAt) return '-';

                    return "<div>
                                <div>{$deletedAt->format('Y-m-d H:i:s')}</div>
                                <small class='text-muted'>{$video->getDeletedAtFormattedAttribute()}</small>
                            </div>";
                }),

            TD::make('encoding_progress', __('Progress'))
                ->render(function (Video $video) {
                    $progress = $video->getEncodingProgress();
                    return "<div class='progress' style='width: 80px;'>
                                <div class='progress-bar' role='progressbar' style='width: {$progress}%' aria-valuenow='{$progress}' aria-valuemin='0' aria-valuemax='100'>
                                    {$progress}%
                                </div>
                            </div>";
                }),

            TD::make(__('Actions'))
                ->render(function (Video $video) {
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list([
                            Button::make(__('Restore'))
                                ->icon('bs.arrow-clockwise')
                                ->method('restore', ['video' => $video->getKey()])
                                ->confirm(__('Are you sure you want to restore this video?')),

                            Button::make(__('Delete Permanently'))
                                ->icon('bs.trash3')
                                ->method('forceDelete', ['video' => $video->getKey()])
                                ->confirm(__('Are you sure you want to permanently delete this video? This action cannot be undone.')),
                        ]);
                }),
        ];
    }

    /**
     * Get the layout for the table.
     */
    public function layout(): array
    {
        return [
            OrbitLayout::table(__('Deleted Videos'), $this->columns()),
        ];
    }
}
