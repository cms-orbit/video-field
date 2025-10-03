<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Screens;

use CmsOrbit\VideoField\Entities\Video\Layouts\VideoTrashLayout;
use CmsOrbit\VideoField\Entities\Video\Video;
use App\Settings\Extends\OrbitLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Illuminate\Support\Facades\Auth;
use Orchid\Support\Facades\Toast;
use App\Settings\Entities\User\User;

class VideoTrashScreen extends Screen
{
    /**
     * Query data.
     */
    public function query(): iterable
    {
        return [
            'videos' => Video::onlyTrashed()
                ->filters()
                ->defaultSort('deleted_at', 'desc')
                ->with(['profiles'])
                ->paginate(),
            'trash_count' => Video::onlyTrashed()->count(),
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        $count = $this->query()['trash_count'];
        return $count > 0 ? __('Trash (:count)', ['count' => $count]) : __('Trash');
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return __('Manage deleted videos');
    }

    /**
     * Button commands.
     */
    public function commandBar(): iterable
    {
        return [
            \Orchid\Screen\Actions\Button::make(__('Empty Trash'))
                ->icon('bs.trash3')
                ->confirm(__('Are you sure you want to permanently delete all videos in trash?'))
                ->method('emptyTrash')
                ->class('btn btn-danger'),
        ];
    }

    /**
     * Views.
     */
    public function layout(): iterable
    {
        return [
            VideoTrashLayout::class,
        ];
    }

    /**
     * Restore video from trash.
     */
    public function restore(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $videoId = $request->get('video');
        $video = Video::onlyTrashed()->findOrFail($videoId);

        if ($user->hasAccess('settings.entities.videos.restore')) {
            $video->restore();
            Toast::success(__('Video has been restored.'));
        } else {
            Toast::error(__('You do not have permission to restore this video.'));
        }

        return redirect()->route('settings.entities.videos.trash');
    }

    /**
     * Permanently delete video.
     */
    public function forceDelete(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $videoId = $request->get('video');
        $video = Video::onlyTrashed()->findOrFail($videoId);

        if ($user->hasAccess('settings.entities.videos.delete')) {
            $video->forceDelete();
            Toast::success(__('Video has been permanently deleted.'));
        } else {
            Toast::error(__('You do not have permission to permanently delete this video.'));
        }

        return redirect()->route('settings.entities.videos.trash');
    }

    /**
     * Empty all videos from trash.
     */
    public function emptyTrash(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasAccess('settings.entities.videos.delete')) {
            $videos = Video::onlyTrashed()->get();
            $count = $videos->count();

            foreach($videos as $video) $video->forceDelete();

            Toast::success(__(':count videos have been permanently deleted.', ['count' => $count]));
        } else {
            Toast::error(__('You do not have permission to empty trash.'));
        }

        return redirect()->route('settings.entities.videos.trash');
    }
}
