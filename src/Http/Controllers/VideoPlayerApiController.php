<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Http\Controllers;

use App\Http\Controllers\Controller;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoWatchHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VideoPlayerApiController extends Controller
{
    /**
     * Get video details for player.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $video = Video::with(['originalFile', 'profiles'])->findOrFail($id);

        // 시청 기록 조회
        $watchHistory = $this->getOrCreateWatchHistory($request, $id);

        return response()->json([
            'id' => $video->getAttribute('id'),
            'title' => $video->getAttribute('title'),
            'description' => $video->getAttribute('description'),
            'filename' => $video->originalFile?->getAttribute('original_name') ?? $video->getAttribute('title'),
            'duration' => $video->getAttribute('duration'),
            'file_size' => $video->originalFile?->getAttribute('size') ?? 0,
            'status' => $video->getAttribute('status'),
            'thumbnail_url' => $video->getThumbnailUrl(),
            'sprite_url' => $video->getScrubbingSpriteUrl(),
            'sprite_metadata' => $video->getSpriteMetadata(),
            'created_at' => $video->getAttribute('created_at'),
            'encoding_progress' => $video->getEncodingProgress(),
            'streaming' => [
                'dash' => $video->getDashManifestUrl(),
                'hls' => $video->getHlsManifestUrl(),
                'progressive' => $video->getProgressiveUrl(),
            ],
            'profiles' => $video->profiles->map(function ($profile) {
                return [
                    'profile' => $profile->getAttribute('profile'),
                    'encoded' => $profile->getAttribute('encoded'),
                    'resolution' => $profile->getResolution(),
                    'quality_label' => $profile->getQualityLabel(),
                    'url' => $profile->getUrl(),
                    'width' => $profile->getAttribute('width'),
                    'height' => $profile->getAttribute('height'),
                ];
            }),
            'watch_history' => $watchHistory ? [
                'seconds' => $watchHistory->getAttribute('seconds'),
                'played' => $watchHistory->getAttribute('played'),
                'percent' => $watchHistory->getAttribute('percent'),
                'is_complete' => $watchHistory->getAttribute('is_complete'),
            ] : null,
        ]);
    }

    /**
     * Get or create watch history for current user/session.
     * 비회원으로 시청하다가 로그인하면 세션 기록을 유저에게 자동으로 연결
     */
    private function getOrCreateWatchHistory(Request $request, int $videoId): ?VideoWatchHistory
    {
        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $query = VideoWatchHistory::where('video_id', $videoId);

        if ($user) {
            // 로그인한 경우: 먼저 유저 기록 찾기
            $history = (clone $query)
                ->where('watcher_id', $user->getAttribute('id'))
                ->where('watcher_type', get_class($user))
                ->first();

            // 유저 기록이 없으면 세션 기록 찾아서 유저에게 연결
            if (!$history) {
                $sessionHistory = (clone $query)
                    ->where('session_id', $sessionId)
                    ->whereNull('watcher_id')
                    ->first();

                if ($sessionHistory) {
                    // 세션 기록을 유저에게 연결
                    $sessionHistory->watcher()->associate($user);
                    $sessionHistory->save();
                    $history = $sessionHistory;
                }
            }
        } else {
            // 비로그인: 세션 기록 찾기
            $history = $query
                ->where('session_id', $sessionId)
                ->whereNull('watcher_id')
                ->first();
        }

        // 기록이 없으면 새로 생성
        if (!$history && config('orbit-video.player.auto_save_progress', true)) {
            $history = new VideoWatchHistory();
            $history->setAttribute('video_id', $videoId);
            $history->setAttribute('session_id', $sessionId);
            
            if ($user) {
                $history->watcher()->associate($user);
            }
            
            $history->save();
        }

        return $history;
    }

    /**
     * Record video play event.
     */
    public function recordPlay(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'timestamp' => 'nullable|numeric|min:0',
            'quality' => 'nullable|string',
        ]);

        $video = Video::findOrFail($id);

        // TODO: 재생 시작 이벤트 로깅
        // - 사용자 ID (로그인한 경우)
        // - IP 주소
        // - User Agent
        // - 재생 시작 시간
        // - 선택된 화질

        return response()->json([
            'success' => true,
            'message' => 'Play event recorded',
        ]);
    }

    /**
     * Record video pause event.
     */
    public function recordPause(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'timestamp' => 'required|numeric|min:0',
            'duration' => 'nullable|numeric|min:0',
        ]);

        $video = Video::findOrFail($id);

        // TODO: 일시정지 이벤트 로깅
        // - 일시정지 시점
        // - 재생된 시간

        return response()->json([
            'success' => true,
            'message' => 'Pause event recorded',
        ]);
    }

    /**
     * Record video progress.
     */
    public function recordProgress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'current_time' => 'required|numeric|min:0',
            'duration' => 'required|numeric|min:0',
        ]);

        $video = Video::findOrFail($id);
        $currentTime = (float) $request->get('current_time');
        $duration = (float) $request->get('duration');

        $watchHistory = $this->getOrCreateWatchHistory($request, $id);

        if ($watchHistory) {
            $watchHistory->updateProgress($currentTime, $duration);

            return response()->json([
                'success' => true,
                'message' => 'Progress recorded',
                'data' => [
                    'seconds' => $watchHistory->getAttribute('seconds'),
                    'played' => $watchHistory->getAttribute('played'),
                    'percent' => $watchHistory->getAttribute('percent'),
                    'is_complete' => $watchHistory->getAttribute('is_complete'),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to record progress',
        ], 500);
    }

    /**
     * Record video complete event.
     */
    public function recordComplete(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'watched_duration' => 'required|numeric|min:0',
            'completed_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $video = Video::findOrFail($id);

        // TODO: 완료 이벤트 로깅
        // - 총 시청 시간
        // - 완료 비율
        // - 시청 완료 여부 (90% 이상 시청 등)

        return response()->json([
            'success' => true,
            'message' => 'Complete event recorded',
        ]);
    }

    /**
     * Get watch history for lecture mode.
     */
    public function watchHistory(Request $request, int $id): JsonResponse
    {
        $video = Video::findOrFail($id);
        $watchHistory = $this->getOrCreateWatchHistory($request, $id);

        if ($watchHistory) {
            return response()->json([
                'success' => true,
                'data' => [
                    'seconds' => $watchHistory->getAttribute('seconds'),
                    'played' => $watchHistory->getAttribute('played'),
                    'percent' => $watchHistory->getAttribute('percent'),
                    'is_complete' => $watchHistory->getAttribute('is_complete'),
                    'max_seekable_time' => $watchHistory->getMaxSeekableTime(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'seconds' => 0,
                'played' => 0,
                'percent' => 0,
                'is_complete' => false,
                'max_seekable_time' => 0,
            ],
        ]);
    }

    /**
     * Get or save video playback position.
     *
     * @deprecated Use recordProgress instead
     */
    public function position(Request $request, int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        if ($request->isMethod('POST')) {
            // Save position
            $request->validate([
                'position' => 'required|numeric|min:0',
            ]);

            $watchHistory = $this->getOrCreateWatchHistory($request, $id);

            if ($watchHistory) {
                $duration = $video->getAttribute('duration') ?? 0;
                $watchHistory->updateProgress((float) $request->get('position'), $duration);

                return response()->json([
                    'success' => true,
                    'position' => $request->get('position'),
                    'message' => 'Position saved',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to save position',
            ], 500);
        }

        // Get saved position
        $watchHistory = $this->getOrCreateWatchHistory($request, $id);
        $savedPosition = $watchHistory?->getAttribute('seconds') ?? 0;

        return response()->json([
            'position' => $savedPosition,
        ]);
    }

    /**
     * Increment view count.
     */
    public function incrementView(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        // TODO: 조회수 증가 로직
        // - 중복 조회 방지 (IP, 세션, 시간 기반)
        // - 조회수 캐싱
        // - 비동기 처리

        return response()->json([
            'success' => true,
            'message' => 'View count incremented',
        ]);
    }

    /**
     * Report video quality or playback issue.
     */
    public function reportIssue(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'issue_type' => 'required|string|in:quality,buffering,audio,subtitle,other',
            'description' => 'nullable|string|max:500',
            'timestamp' => 'nullable|numeric|min:0',
            'quality_profile' => 'nullable|string',
        ]);

        $video = Video::findOrFail($id);

        // TODO: 재생 문제 리포트
        // - 문제 유형
        // - 발생 시점
        // - 사용자 환경 정보
        // - 로그 기록

        return response()->json([
            'success' => true,
            'message' => 'Issue report submitted',
        ]);
    }

    /**
     * Get video analytics data.
     */
    public function analytics(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        // TODO: 비디오 분석 데이터
        // - 총 재생 횟수
        // - 평균 시청 시간
        // - 시청 완료율
        // - 화질별 선택 비율
        // - 시간대별 재생 패턴

        return response()->json([
            'total_views' => 0,
            'average_watch_time' => 0,
            'completion_rate' => 0,
            'quality_distribution' => [],
        ]);
    }
}

