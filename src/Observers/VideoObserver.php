<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Observers;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoProcessJob;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoObserver
{
    //['uploading','upload_failed','uploaded','pending', 'processing', 'completed', 'failed']
    /**
     * When a video is created.
     */
    public function created(Video $video): void
    {
        if($video->originalFile == null){
            $video->setAttribute('status','upload_failed');
            $video->save();
        }else{
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('orbit-video.ffmpeg.binary_path'),
                'ffprobe.binaries' => config('orbit-video.ffmpeg.ffprobe_path'),
                'timeout' => config('orbit-video.ffmpeg.timeout'),
                'ffmpeg.threads' => config('orbit-video.ffmpeg.threads'),
            ]);
            $ffprobe = $ffmpeg->getFFProbe();

            $originalFilePath = $video->getVideoPath();
            $videoInfo = $ffprobe->format($originalFilePath);
            $videoStream = $ffprobe->streams($originalFilePath)->videos()->first();

            $duration = (float)$videoInfo->get('duration');
            $width = (int)$videoStream->get('width');
            $height = (int)$videoStream->get('height');
            $framerate = (float)$videoStream->get('r_frame_rate');
            $bitrate = (int)$videoInfo->get('bit_rate');

            $video->update([
                'title' => $video->originalFile->getAttribute('original_name'),
                'duration' => $duration,
                'original_width' => $width,
                'original_height' => $height,
                'original_framerate' => $framerate,
                'original_bitrate' => $bitrate,
                'status' => 'uploaded'
            ]);
        }
    }

    /**
     * Dispatch processing when status moves to 'pending' from other states.
     */
    public function updated(Video $video): void
    {
        if ($video->getAttribute('status') === 'uploaded') {
            $video->update(['status' => 'pending']);
            VideoProcessJob::dispatch($video);
        }
    }

    /**
     * Clean up storage on delete.
     */
    public function forceDeleting(Video $video): void
    {
        try {
            $video->profiles()->delete();
            $video->originalFile->delete();

            $disk = config('orbit-video.storage.disk', 'public');
            $videoPath = $video->getVideoPath();
            if (Storage::disk($disk)->exists($videoPath)) {
                Storage::disk($disk)->deleteDirectory($videoPath);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to cleanup video files on delete', [
                'video_id' => $video->getAttribute('id'),
                'error' => $e->getMessage(),
            ]);
        }
    }
}


