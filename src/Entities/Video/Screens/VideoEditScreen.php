<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Screens;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\Layouts\VideoEditLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Toast;

class VideoEditScreen extends Screen
{
    /**
     * @var Video
     */
    public $video;

    /**
     * Query data.
     */
    public function query(Video $video): iterable
    {
        return [
            'video' => $video,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return $this->video->exists ? __('Edit Video') : __('Create Video');
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return __('Video details and encoding status');
    }

    /**
     * Button commands.
     */
    public function commandBar(): iterable
    {
        return [
            Button::make(__('Save'))
                ->icon('bs.check-circle')
                ->method('save'),

            Button::make(__('Remove'))
                ->icon('bs.trash3')
                ->confirm(__('Are you sure you want to delete this video?'))
                ->method('remove')
                ->canSee($this->video->exists),
        ];
    }

    /**
     * Views.
     */
    public function layout(): iterable
    {
        return [
            VideoEditLayout::class,
        ];
    }

    /**
     * @param Request $request
     * @param Video   $video
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request, Video $video)
    {
        $request->validate([
            'video.title' => 'required|string|max:255',
            'video.description' => 'nullable|string',
            'video.status' => 'required|in:pending,processing,completed,failed',
        ]);

        $video->fill($request->get('video'))->save();

        Toast::info(__('Video was saved.'));

        return redirect()->route('settings.entities.videos.index');
    }

    /**
     * @param Video $video
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(Video $video)
    {
        $video->delete();

        Toast::info(__('Video was removed.'));

        return redirect()->route('settings.entities.videos.index');
    }
}
