<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;

class VideoController extends Controller
{
    /**
     * Display a listing of videos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Video::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        $videos = $query->with('profiles')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($videos);
    }

    /**
     * Display the specified video.
     */
    public function show(Video $video): JsonResponse
    {
        $video->load(['profiles', 'encodingLogs']);

        return response()->json([
            'video' => $video,
            'metadata' => [
                'duration' => $video->getReadableDuration(),
                'file_size' => $video->getReadableSize(),
                'encoding_progress' => $video->getEncodingProgress(),
                'available_profiles' => $video->getAvailableProfiles(),
                'sprite_metadata' => $video->getSpriteMetadata(),
            ],
        ]);
    }

    /**
     * Store a newly created video.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'original_filename' => 'required|string',
            'original_size' => 'required|integer',
            'mime_type' => 'required|string',
            'user_id' => 'nullable|integer',
        ]);

        $video = Video::create($request->all());

        return response()->json($video, 201);
    }

    /**
     * Update the specified video.
     */
    public function update(Request $request, Video $video): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:pending,processing,completed,failed',
        ]);

        $video->update($request->all());

        return response()->json($video);
    }

    /**
     * Remove the specified video.
     */
    public function destroy(Video $video): JsonResponse
    {
        $video->delete();

        return response()->json(['message' => 'Video deleted successfully']);
    }

    /**
     * Stream video for specific profile.
     */
    public function stream(Video $video, string $profile): Response
    {
        $videoProfile = $video->profiles()
            ->where('profile', $profile)
            ->where('encoded', true)
            ->firstOrFail();

        $path = $videoProfile->path;
        $disk = config('video.storage.disk');

        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'Video file not found');
        }

        $file = Storage::disk($disk)->get($path);
        $mimeType = Storage::disk($disk)->mimeType($path);

        return response($file, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($file),
        ]);
    }

    /**
     * Get video thumbnail.
     */
    public function thumbnail(Video $video): Response
    {
        if (!$video->hasThumbnail()) {
            abort(404, 'Thumbnail not found');
        }

        $path = $video->thumbnail_path;
        $disk = config('video.storage.disk');
        $file = Storage::disk($disk)->get($path);

        return response($file, 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => strlen($file),
        ]);
    }

    /**
     * Get video sprite sheet.
     */
    public function sprite(Video $video): Response
    {
        if (!$video->hasSprite()) {
            abort(404, 'Sprite not found');
        }

        $path = $video->scrubbing_sprite_path;
        $disk = config('video.storage.disk');
        $file = Storage::disk($disk)->get($path);

        return response($file, 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => strlen($file),
        ]);
    }

    /**
     * Trigger video encoding.
     */
    public function encode(Video $video): JsonResponse
    {
        // TODO: Dispatch encoding job
        // EncodeVideoJob::dispatch($video);

        return response()->json(['message' => 'Encoding started']);
    }

    /**
     * Get video encoding status.
     */
    public function encodingStatus(Video $video): JsonResponse
    {
        $profiles = $video->profiles()
            ->with('encodingLogs')
            ->get()
            ->map(function ($profile) {
                return [
                    'profile' => $profile->profile,
                    'encoded' => $profile->encoded,
                    'is_encoding' => $profile->isEncoding(),
                    'latest_log' => $profile->getLatestLog(),
                ];
            });

        return response()->json([
            'overall_progress' => $video->getEncodingProgress(),
            'status' => $video->status,
            'profiles' => $profiles,
        ]);
    }
}
