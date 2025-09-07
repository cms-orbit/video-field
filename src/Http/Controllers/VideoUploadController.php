<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\ProcessVideoJob;

class VideoUploadController extends Controller
{
    /**
     * Upload videos using Orchid Attach field
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'videos' => 'required|array',
            'videos.*' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:102400', // 100MB max
        ]);

        try {
            $uploadedVideos = [];
            
            foreach ($request->file('videos') as $file) {
                // Store file using Laravel's file storage
                $path = $file->store('videos', 'public');
                
                // Create video model
                $video = Video::create([
                    'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'status' => 'uploaded',
                ]);

                // Dispatch job for processing (sprite, encoding, etc.)
                ProcessVideoJob::dispatch($video);

                $uploadedVideos[] = [
                    'id' => $video->id,
                    'title' => $video->title,
                    'filename' => $video->filename,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Videos uploaded successfully',
                'videos' => $uploadedVideos,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of videos for selector
     *
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        $videos = Video::select('id', 'title', 'filename')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($videos);
    }

    /**
     * Get video by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        return response()->json([
            'id' => $video->id,
            'title' => $video->title,
            'filename' => $video->filename,
            'path' => $video->path,
            'size' => $video->size,
            'mime_type' => $video->mime_type,
            'status' => $video->status,
            'created_at' => $video->created_at,
        ]);
    }
}
