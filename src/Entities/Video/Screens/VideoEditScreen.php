<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Screens;

use App\Settings\Entities\User\User;
use App\Settings\Extends\OrbitLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\BasicInformationLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\VideoDetailsLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\VideoProfilesLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\EncodingStatusLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\Modals\FfmpegCommandModal;
use CmsOrbit\VideoField\Entities\Video\Layouts\Modals\ErrorOutputModal;
use CmsOrbit\VideoField\Entities\Video\Layouts\Modals\ProfileLogsModal;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoProcessJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

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
        // Load relationships for better performance
        $video->load([
            'profiles.encodingLogs',
            'encodingLogs',
            'originalFile'
        ]);

        return [
            'video' => $video,
            'video.profiles' => $video->profiles,
            'video.encoding_logs' => $video->encodingLogs()->latest()->limit(50)->get(),
            'video.hls_manifest_url' => $video->getHlsManifestUrl(),
            'video.dash_manifest_url' => $video->getDashManifestUrl(),
            'video.thumbnail_url' => $video->getThumbnailUrl(),
            'video.supports_abr' => $video->supportsAbr(),
            'video.abr_profile_count' => count($video->getAvailableProfiles() ?? []),
            'video.abr_profiles' => $video->getAvailableProfiles(),
            'video.player_metadata' => $video->getPlayerMetadata(),
            'video.encoding_progress' => $video->getEncodingProgress(),
            'video.has_thumbnail' => $video->hasThumbnail(),
            'video.has_sprite' => $video->hasSprite(),
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
        $commands = [];

        // Save 버튼 (항상 표시)
        $commands[] = Button::make(__('Save'))
            ->icon('bs.check-circle')
            ->method('save')
            ->class('btn btn-primary')
            ->canSee($this->video->exists || request()->has('video.title'));

        // Remove 버튼 (기존 비디오인 경우)
        $commands[] = Button::make(__('Remove'))
            ->icon('bs.trash3')
            ->confirm(__('Are you sure you want to delete this video?'))
            ->method('remove')
            ->class('btn btn-danger')
            ->canSee($this->video->exists);

        return $commands;
    }

    /**
     * Views.
     */
    public function layout(): array
    {
        return [
            // Video Information Tabs
            OrbitLayout::tabs([
                __('Preview') => OrbitLayout::view('cms-orbit-video::video-player'),
                __('Basic Information') => (new BasicInformationLayout)(),
                __('Video Details') => new VideoDetailsLayout,
                __('Profiles') => new VideoProfilesLayout,
                __('Encoding') => new EncodingStatusLayout,
            ]),

            // Modals
            OrbitLayout::modal('ffmpeg-command-modal', FfmpegCommandModal::class),
            OrbitLayout::modal('error-output-modal', ErrorOutputModal::class),
            OrbitLayout::modal('profile-logs-modal', ProfileLogsModal::class),
        ];
    }

    /**
     * Save video information.
     *
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
        ]);

        // Only allow editing title and description for existing videos
        if ($video->exists) {
            $video->update([
                'title' => $request->get('video.title'),
                'description' => $request->get('video.description'),
            ]);
        } else {
            // For new videos, create with basic info
            $video->fill([
                'title' => $request->get('video.title'),
                'description' => $request->get('video.description'),
                'status' => 'pending',
                'user_id' => auth()->id(),
            ])->save();
        }

        Toast::info(__('Video information was saved.'));

        return redirect()->route('settings.entities.videos');
    }


    /**
     * Remove video.
     *
     * @param Video $video
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(Video $video)
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

    /**
     * Handle video upload from VideoUpload field.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleVideoUpload(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_upload' => 'required|array',
        ]);

        // Create new video record
        $video = Video::create([
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'status' => 'pending',
            'user_id' => auth()->id(),
        ]);

        // The actual video file processing will be handled by the VideoUpload field
        // and the VideoProcessJob will be dispatched automatically

        Toast::info(__('Video uploaded successfully. Processing will begin shortly.'));

        return redirect()->route('settings.entities.videos.edit', $video);
    }

    /**
     * View FFmpeg command details.
     */
    public function viewFfmpegCommand(Request $request)
    {
        $logId = $request->get('log_id');
        $log = \CmsOrbit\VideoField\Entities\Video\VideoEncodingLog::findOrFail($logId);

        return [
            'ffmpegCommandModal.command' => $log->getAttribute('ffmpeg_command'),
        ];
    }

    /**
     * View error output details.
     */
    public function viewErrorOutput(Request $request)
    {
        $logId = $request->get('log_id');
        $log = \CmsOrbit\VideoField\Entities\Video\VideoEncodingLog::findOrFail($logId);

        return [
            'errorOutputModal.error' => $log->getAttribute('error_output'),
        ];
    }

    /**
     * View profile encoding logs.
     */
    public function viewProfileLogs(Request $request)
    {
        $profileId = $request->get('profile_id');
        $profile = \CmsOrbit\VideoField\Entities\Video\VideoProfile::findOrFail($profileId);
        $logs = $profile->encodingLogs()->latest()->get();

        return [
            'profileLogsModal.profile' => $profile,
            'profileLogsModal.logs' => $logs,
        ];
    }

}
