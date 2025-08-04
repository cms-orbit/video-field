<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Screens;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\Layouts\VideoListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;

class VideoListScreen extends Screen
{
    /**
     * Query data.
     */
    public function query(): iterable
    {
        return [
            'videos' => Video::filters()
                ->defaultSort('id', 'desc')
                ->with(['profiles'])
                ->paginate(),
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return __('Videos');
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return __('Manage video files and encoding profiles');
    }

    /**
     * Button commands.
     */
    public function commandBar(): iterable
    {
        return [
            Link::make(__('Upload Video'))
                ->icon('bs.cloud-upload')
                ->route('settings.entities.videos.create'),
        ];
    }

    /**
     * Views.
     */
    public function layout(): iterable
    {
        return [
            VideoListLayout::class,
        ];
    }
}
