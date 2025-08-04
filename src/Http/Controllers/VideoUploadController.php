<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoProcessJob;

class VideoUploadController extends Controller
{
    /**
     * Upload video chunk.
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        $request->validate([
            'chunk' => 'required|file',
            'chunk_number' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'upload_id' => 'required|string',
            'filename' => 'required|string',
        ]);

        $uploadId = $request->get('upload_id');
        $chunkNumber = $request->get('chunk_number');
        $totalChunks = $request->get('total_chunks');
        $filename = $request->get('filename');

        // Store chunk in temporary directory
        $tempDir = "temp/video-uploads/{$uploadId}";
        $chunkPath = "{$tempDir}/chunk_{$chunkNumber}";

        $disk = config('video.storage.disk');
        Storage::disk($disk)->put($chunkPath, $request->file('chunk')->getContent());

        // Check if all chunks are uploaded
        $uploadedChunks = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            if (Storage::disk($disk)->exists("{$tempDir}/chunk_{$i}")) {
                $uploadedChunks[] = $i;
            }
        }

        $isComplete = count($uploadedChunks) === $totalChunks;

        return response()->json([
            'chunk_number' => $chunkNumber,
            'uploaded_chunks' => $uploadedChunks,
            'total_chunks' => $totalChunks,
            'is_complete' => $isComplete,
            'upload_id' => $uploadId,
        ]);
    }

    /**
     * Complete chunked upload and create video record.
     */
    public function completeUpload(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string',
            'filename' => 'required|string',
            'total_chunks' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $uploadId = $request->get('upload_id');
        $filename = $request->get('filename');
        $totalChunks = $request->get('total_chunks');

        $disk = config('video.storage.disk');
        $tempDir = "temp/video-uploads/{$uploadId}";

        // Verify all chunks exist
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!Storage::disk($disk)->exists("{$tempDir}/chunk_{$i}")) {
                return response()->json(['error' => "Missing chunk {$i}"], 400);
            }
        }

        // Create video record first to get ID for path generation
        $video = Video::create([
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'original_filename' => $filename,
            'original_size' => 0, // Will be updated after file combination
            'mime_type' => 'video/mp4', // Will be updated after file validation
            'status' => 'pending',
            'user_id' => auth()->id(),
        ]);

        // Generate final path using videoId placeholder
        $videoPath = $video->getVideoPath();
        $finalPath = $videoPath . '/original_' . $filename;
        $finalContent = '';

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = "{$tempDir}/chunk_{$i}";
            $finalContent .= Storage::disk($disk)->get($chunkPath);
        }

        // Store final file
        Storage::disk($disk)->put($finalPath, $finalContent);

        // Get file info
        $fileSize = strlen($finalContent);
        $mimeType = Storage::disk($disk)->mimeType($finalPath);

        // Validate file type
        $allowedTypes = config('video.upload.allowed_mime_types');
        if (!in_array($mimeType, $allowedTypes)) {
            Storage::disk($disk)->delete($finalPath);
            return response()->json(['error' => 'Invalid file type'], 400);
        }

        // Extract video metadata (basic implementation)
        $duration = null; // TODO: Extract using FFmpeg
        $metadata = []; // TODO: Extract metadata

        // Update video record with final information
        $video->update([
            'original_size' => $fileSize,
            'duration' => $duration,
            'mime_type' => $mimeType,
            'meta_data' => $metadata,
        ]);

        // Dispatch complete video processing job
        dispatch(new VideoProcessJob($video, false));

        \Log::info("Video upload completed and processing job dispatched", [
            'video_id' => $video->getAttribute('id'),
            'filename' => $filename,
            'size' => $fileSize
        ]);

        // Clean up temporary files
        Storage::disk($disk)->deleteDirectory($tempDir);

        return response()->json([
            'video' => $video,
            'message' => 'Upload completed successfully',
        ], 201);
    }

    /**
     * Cancel upload and clean up temporary files.
     */
    public function cancelUpload(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string',
        ]);

        $uploadId = $request->get('upload_id');
        $disk = config('video.storage.disk');
        $tempDir = "temp/video-uploads/{$uploadId}";

        // Clean up temporary files
        if (Storage::disk($disk)->exists($tempDir)) {
            Storage::disk($disk)->deleteDirectory($tempDir);
        }

        return response()->json(['message' => 'Upload cancelled']);
    }
}
