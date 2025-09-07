<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use CmsOrbit\VideoField\Entities\Video\Video;

class VideoController extends Controller
{
    /**
     * Display a listing of videos.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $videos = Video::with(['profiles'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('cms-orbit-video::videos.index', compact('videos'));
    }

    /**
     * Display the specified video.
     *
     * @param Video $video
     * @return \Illuminate\View\View
     */
    public function show(Video $video)
    {
        $video->load(['profiles', 'encodingLogs']);

        return view('cms-orbit-video::videos.show', compact('video'));
    }

    /**
     * Get videos by IDs for field display
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getByIds(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:videos,id',
        ]);

        $videos = Video::whereIn('id', $request->get('ids'))
            ->select('id', 'title', 'filename', 'status')
            ->get();

        return response()->json($videos);
    }
}