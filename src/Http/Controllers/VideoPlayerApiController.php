<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Http\Controllers;

use App\Http\Controllers\Controller;
use CmsOrbit\VideoField\Entities\Video\Video;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoPlayerApiController extends Controller
{
    /**
     * Get video details for player.
     */
    public function show(int $id): JsonResponse
    {
        $video = Video::with(['originalFile', 'profiles'])->findOrFail($id);

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
        ]);
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
            'timestamp' => 'required|numeric|min:0',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        $video = Video::findOrFail($id);

        // TODO: 시청 진행률 기록
        // - 현재 재생 위치
        // - 시청 완료 비율
        // - 세션 ID

        return response()->json([
            'success' => true,
            'message' => 'Progress recorded',
        ]);
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
     * Get or save video playback position.
     */
    public function position(Request $request, int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        if ($request->isMethod('POST')) {
            // Save position
            $request->validate([
                'position' => 'required|numeric|min:0',
            ]);

            // TODO: 재생 위치 저장
            // - 사용자별 마지막 재생 위치 저장
            // - 로그인하지 않은 경우 세션 또는 로컬스토리지 활용

            return response()->json([
                'success' => true,
                'position' => $request->get('position'),
                'message' => 'Position saved',
            ]);
        }

        // Get saved position
        // TODO: 저장된 재생 위치 불러오기
        $savedPosition = 0; // 실제로는 DB나 세션에서 가져와야 함

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

