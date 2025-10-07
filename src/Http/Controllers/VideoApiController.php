<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Http\Controllers;

use App\Http\Controllers\Controller;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoEncodeJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Orchid\Attachment\Models\Attachment;
use Illuminate\Support\Str;

class VideoApiController extends Controller
{
    /**
     * Search videos by title or filename.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = (int) $request->get('limit', 10);

        $videos = Video::query()
            ->with('originalFile')
            ->when($query, function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhereHas('originalFile', function($q) use ($query) {
                      $q->where('original_name', 'like', "%{$query}%");
                  });
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->getAttribute('id'),
                    'title' => $video->getAttribute('title'),
                    'filename' => $video->originalFile?->getAttribute('original_name') ?? $video->getAttribute('title'),
                    'duration' => $video->getAttribute('duration'),
                    'file_size' => $video->originalFile?->getAttribute('size') ?? 0,
                    'status' => $video->getAttribute('status'),
                    'thumbnail_url' => $video->getThumbnailUrl(),
                    'created_at' => $video->getAttribute('created_at'),
                ];
            });

        return response()->json([
            'data' => $videos,
            'total' => $videos->count(),
        ]);
    }

    /**
     * Get recent videos.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 10);

        $videos = Video::query()
            ->with('originalFile')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->getAttribute('id'),
                    'title' => $video->getAttribute('title'),
                    'filename' => $video->originalFile?->getAttribute('original_name') ?? $video->getAttribute('title'),
                    'duration' => $video->getAttribute('duration'),
                    'file_size' => $video->originalFile?->getAttribute('size') ?? 0,
                    'status' => $video->getAttribute('status'),
                    'thumbnail_url' => $video->getThumbnailUrl(),
                    'created_at' => $video->getAttribute('created_at'),
                ];
            });

        return response()->json([
            'data' => $videos,
            'total' => $videos->count(),
        ]);
    }

    /**
     * Upload new video.
     */
    public function upload(Request $request): JsonResponse
    {
        $maxFileSizeKB = (int) ceil(config('orbit-video.upload.max_file_size') / 1024);
        $allowedMimes = implode(',', config('orbit-video.upload.allowed_extensions'));
        
        $request->validate([
            'video' => "required|file|mimes:{$allowedMimes}|max:{$maxFileSizeKB}",
        ]);

        try {
            $file = $request->file('video');
            $originalName = $file->getClientOriginalName();
            $filename = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $uniqueFilename = Str::uuid() . '.' . $extension;

            // Store the file using Orchid's attachment system
            $attachment = new Attachment();
            $attachment->name = $originalName;
            $attachment->original_name = $originalName;
            $attachment->mime = $file->getMimeType();
            $attachment->size = $file->getSize();
            $attachment->hash = md5_file($file->getPathname());
            $attachment->disk = config('orbit-video.storage.disk');
            $attachment->path = sprintf('orbit-video-original/%s/%s', date('Y/m/d'), $uniqueFilename);
            
            // Store file to disk
            Storage::disk($attachment->disk)->put($attachment->path, file_get_contents($file->getPathname()));
            
            $attachment->save();

            // Create Video model
            $video = new Video();
            $video->setAttribute('title', $filename);
            $video->setAttribute('original_file_id', $attachment->getAttribute('id'));
            $video->setAttribute('status', 'pending');
            $video->setAttribute('user_id', auth()->id());
            $video->save();

            // Dispatch encoding job
            VideoEncodeJob::dispatch($video);

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully',
                'video' => [
                    'id' => $video->getAttribute('id'),
                    'title' => $video->getAttribute('title'),
                    'filename' => $video->originalFile?->getAttribute('original_name') ?? $video->getAttribute('title'),
                    'duration' => $video->getAttribute('duration'),
                    'file_size' => $video->originalFile?->getAttribute('size') ?? 0,
                    'status' => $video->getAttribute('status'),
                    'thumbnail_url' => $video->getThumbnailUrl(),
                    'created_at' => $video->getAttribute('created_at'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create video from existing attachment.
     */
    public function createFromAttachment(Request $request): JsonResponse
    {
        $request->validate([
            'attachment_id' => 'required|exists:attachments,id',
        ]);

        try {
            $attachment = Attachment::findOrFail($request->attachment_id);
            
            // Create Video model from attachment
            $video = new Video();
            $video->setAttribute('title', pathinfo($attachment->getAttribute('original_name'), PATHINFO_FILENAME));
            $video->setAttribute('original_file_id', $attachment->getAttribute('id'));
            $video->setAttribute('status', 'pending');
            $video->setAttribute('user_id', auth()->id());
            $video->save();

            // Dispatch encoding job
            VideoEncodeJob::dispatch($video);

            return response()->json([
                'success' => true,
                'message' => 'Video created from attachment successfully',
                'video' => [
                    'id' => $video->getAttribute('id'),
                    'title' => $video->getAttribute('title'),
                    'filename' => $attachment->getAttribute('original_name'),
                    'duration' => $video->getAttribute('duration'),
                    'file_size' => $attachment->getAttribute('size'),
                    'status' => $video->getAttribute('status'),
                    'thumbnail_url' => $video->getThumbnailUrl(),
                    'created_at' => $video->getAttribute('created_at'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create video from attachment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
