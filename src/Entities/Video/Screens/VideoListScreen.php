<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Screens;

use CmsOrbit\VideoField\Entities\Video\Layouts\VideoUploadLayout;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\Layouts\VideoListLayout;
use App\Settings\Extends\OrbitLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Illuminate\Support\Facades\Auth;
use Orchid\Support\Facades\Toast;
use App\Settings\Entities\User\User;

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
                ->where('deleted_at', null)
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
            ModalToggle::make(__('Upload Video'))
                ->icon('bs.cloud-upload')
                ->modal('videoUploadModal')
                ->method('handleUpload')
                ->class('btn btn-primary'),

            Button::make(__('Trash'))
                ->icon('bs.trash3')
                ->route('settings.entities.videos.trash')
                ->class('btn btn-secondary')
                ->parameters([
                    'count' => $this->query()['trash_count']
                ]),
        ];
    }

    /**
     * Views.
     */
    public function layout(): iterable
    {
        return [
            VideoListLayout::class,

            OrbitLayout::modal('videoUploadModal', [
                VideoUploadLayout::class
            ])->title(__('Upload New Video'))
                ->applyButton(__('Upload'))
                ->size(Modal::SIZE_LG)
                ->closeButton(__('Cancel')),
        ];
    }


    /**
     * Delete video.
     */
    public function remove(Video $video): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasAccess('settings.entities.videos.delete')) {
            $video->delete();
            Toast::info(__('Video was removed.'));
        } else {
            Toast::error(__('You do not have permission to delete this Video.'));
        }

        return redirect()->route('settings.entities.videos');
    }

    public function handleUpload(Request $request)
    {
        $user = Auth::user();
        $uploadedVideos = $request->get('video_upload',[]);
        foreach($uploadedVideos as $uploadedVideo){
            $video = Video::query()->create([
                'title' => 'Unknown',
                'user_id' => $user->getKey(),
                'original_file_id' => $uploadedVideo,
            ]);
        }
        return redirect()->route('settings.entities.videos');
    }
}
