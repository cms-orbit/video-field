<?php

namespace CmsOrbit\VideoField\Entities\Video\Layouts;

use Orchid\Screen\Fields\Attach;
use Orchid\Screen\Layouts\Rows;

class VideoUploadLayout extends Rows
{
    /**
     * @throws \Throwable
     */
    protected function fields(): iterable
    {
        return [
            Attach::make('video_upload')
                ->title(__('Video File'))
                ->help(__('Upload a video file'))
                ->group('video')
                ->multiple()
                ->storage(config('orbit-video.storage.disk'))
                ->path(sprintf('orbit-video-original/%s', date('Y/m/d')))
                ->accept('video/*'),
        ];
    }
}
