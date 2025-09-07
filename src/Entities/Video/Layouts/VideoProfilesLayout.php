<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Input;

class VideoProfilesLayout extends Table
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
                    return $profile->getAttribute('profile');
                }),

            TD::make('quality', __('Quality'))
                ->render(function ($profile) {
                    return $profile->getQualityLabel();
                }),

            TD::make('resolution', __('Resolution'))
                ->render(function ($profile) {
                    return $profile->getResolution();
                }),

            TD::make('framerate', __('Frame Rate'))
                ->render(function ($profile) {
                    return $profile->getAttribute('framerate') . ' fps';
                }),

            TD::make('file_size', __('File Size'))
                ->render(function ($profile) {
                    $size = $profile->getAttribute('file_size');
                    if (!$size) return 'N/A';

                    $units = ['B', 'KB', 'MB', 'GB'];
                    $unitIndex = 0;
                    while ($size >= 1024 && $unitIndex < count($units) - 1) {
                        $size /= 1024;
                        $unitIndex++;
                    }

                    return round($size, 2) . ' ' . $units[$unitIndex];
                }),

            TD::make('encoded', __('Status'))
                ->render(function ($profile) {
                    $encoded = $profile->getAttribute('encoded');
                    $isEncoding = $profile->isEncoding();

                    if ($isEncoding) {
                        return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">' . __('Encoding') . '</span>';
                    } elseif ($encoded) {
                        return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">' . __('Completed') . '</span>';
                    } else {
                        return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">' . __('Pending') . '</span>';
                    }
                }),

            TD::make('path', __('Path'))
                ->render(function ($profile) {
                    $path = $profile->getAttribute('path');
                    if (!$path) return 'N/A';

                    return '<code class="text-xs">' . e($path) . '</code>';
                }),

            TD::make('url', __('URL'))
                ->render(function ($profile) {
                    $url = $profile->getUrl();
                    if (!$url) return 'N/A';

                    return Link::make(__('View'))
                        ->href($url)
                        ->target('_blank')
                        ->icon('bs.box-arrow-up-right');
                }),

            TD::make('created_at', __('Created At'))
                ->render(function ($profile) {
                    return $profile->getAttribute('created_at')?->format('Y-m-d H:i:s');
                }),

            TD::make('actions', __('Actions'))
                ->render(function ($profile) {
                    return ModalToggle::make(__('View Logs'))
                        ->icon('bs.journal-text')
                        ->modal('profile-logs-modal')
                        ->method('viewProfileLogs')
                        ->parameters(['profile_id' => $profile->getAttribute('id')])
                        ->class('btn btn-sm btn-outline-info');
                }),
        ];
    }

}
