<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Screens;

use App\Settings\Entities\User\User;
use App\Settings\Extends\OrbitLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\BasicInformationLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\VideoProfilesAndEncodingLayout;
use CmsOrbit\VideoField\Entities\Video\Layouts\Modals\FfmpegCommandModal;
use CmsOrbit\VideoField\Entities\Video\Layouts\Modals\ErrorOutputModal;
use CmsOrbit\VideoField\Entities\Video\Layouts\Modals\ProfileLogsModal;

use CmsOrbit\VideoField\Entities\Video\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Orchid\Attachment\Models\Attachment;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Picture;
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
            'video.progressive_url' => $video->getProgressiveUrl(),
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

        // Regenerate Manifests 버튼 (인코딩이 완료된 경우)
        $commands[] = Button::make(__('Regenerate Manifests'))
            ->icon('bs.arrow-clockwise')
            ->method('regenerateManifests')
            ->class('btn btn-warning')
            ->canSee($this->video->exists && $this->video->getAttribute('status') === 'completed');

        // Remove 버튼 (기존 비디오인 경우)
        $commands[] = Button::make(__('Remove'))
            ->icon('bs.trash3')
            ->confirm(__('Are you sure you want to delete this video?'))
            ->method('remove')
            ->class('btn btn-danger')
            ->canSee($this->video->exists);

        // Trash 버튼 (기존 비디오인 경우)
        $commands[] = Button::make(__('Trash'))
            ->icon('bs.trash3')
            ->route('settings.entities.videos.trash')
            ->class('btn btn-secondary')
            ->canSee($this->video->exists);

        return $commands;
    }

    /**
     * Views.
     */
    public function layout(): array
    {
        return [
            OrbitLayout::columns([
                OrbitLayout::rows([
                    Picture::make('video.thumbnail_url')
                        ->groups('video_thumbnail')
                        ->targetId()
                        ->path($this->video->getThumbnailPath())
                        ->storage(config('orbit-video.storage.disk'))
                        ->title(__('Thumbnail')),
                ]),
                OrbitLayout::blank([
                    new BasicInformationLayout,
                    new VideoProfilesAndEncodingLayout,
                ])
            ]),
            OrbitLayout::view('cms-orbit-video::video-file-info'),
            OrbitLayout::view('cms-orbit-video::video-player'),

            // Modals
            OrbitLayout::modal('ffmpeg-command-modal', FfmpegCommandModal::class)
                ->deferred('viewFfmpegCommand'),
            OrbitLayout::modal('error-output-modal', ErrorOutputModal::class)
                ->deferred('viewErrorOutput'),
            OrbitLayout::modal('profile-logs-modal', ProfileLogsModal::class)
                ->deferred('viewProfileLogs'),
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
        $args = $request->get('video');

        $attachmentId = Arr::get($args,'thumbnail_url');
        if($attachmentId){
            /** @var Attachment $thumbnail */
            $thumbnail = Attachment::query()->findOrFail($attachmentId);
            $video->setAttribute('thumbnail_path',$thumbnail->physicalPath());
        }

        $video->setAttribute('title',Arr::get($args,'title'));
        $video->setAttribute('description',Arr::get($args,'description'));
        $video->save();

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

        // Convert logs to JSON format for display
        $logsData = $logs->map(function ($log) {
            return [
                'id' => $log->getAttribute('id'),
                'status' => $log->getAttribute('status'),
                'progress' => $log->getAttribute('progress'),
                'message' => $log->getAttribute('message'),
                'processing_time' => $log->getReadableProcessingTime(),
                'ffmpeg_command' => $log->getAttribute('ffmpeg_command'),
                'error_output' => $log->getAttribute('error_output'),
                'created_at' => $log->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            ];
        });

        return [
            'profileLogsModal.logs' => json_encode($logsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Regenerate manifests for this video.
     */
    public function regenerateManifests(Video $video)
    {
        try {
            $video->regenerateManifests();
            Toast::success(__('Manifests have been regenerated successfully.'));
        } catch (\Exception $e) {
            Toast::error(__('Failed to regenerate manifests: ') . $e->getMessage());
        }

        return redirect()->route('settings.entities.videos.edit', $video);
    }

}
