<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Traits\VideoJobTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Exception;

class VideoProcessJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, VideoJobTrait;

    public $timeout = 7200; // 2 hours
    public $tries = 2;

    protected Video $video;
    protected bool $force;
    protected ?array $modelProfiles;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, bool $force = false, ?array $modelProfiles = null)
    {
        $this->video = $video;
        $this->force = $force;
        $this->modelProfiles = $modelProfiles;
    }

    /**
     * Get the video instance.
     */
    public function getVideo(): Video
    {
        return $this->video;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $videoId = $this->video->getAttribute('id');
            $this->logJobStart('video processing', $videoId);

            // Update video status
            $this->video->update(['status' => 'processing']);

            // Chain jobs in sequence
            $this->processVideoSequentially();

            $this->logJobCompletion('video processing job chain started', $videoId);

        } catch (Exception $e) {
            $this->video->update(['status' => 'failed']);
            $this->logJobError('video processing', $this->video->getAttribute('id'), $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->video->update(['status' => 'failed']);

        $this->logJobError('video processing', $this->video->getAttribute('id'), $exception->getMessage());
    }

    /**
     * Process video with job chaining.
     */
    private function processVideoSequentially(): void
    {
        $queueName = config('orbit-video.queue.queue_name', 'encode_video');

        // Create jobs
        $encodeJob = (new VideoEncodeJob($this->video, $this->modelProfiles, $this->force))
            ->onQueue($queueName);

        $thumbnailJob = (new VideoThumbnailJob($this->video, 5, $this->force))
            ->onQueue($queueName);

        $spriteJob = (new VideoSpriteJob($this->video, 100, 10, 10, $this->force))
            ->onQueue($queueName);

        $manifestJob = (new VideoManifestJob($this->video))
            ->onQueue($queueName);

        // Chain jobs using Bus::chain()
        \Illuminate\Support\Facades\Bus::chain([
            $encodeJob,
            $thumbnailJob,
            $spriteJob,
            $manifestJob
        ])->onQueue($queueName)->dispatch();

        Log::info("Job chain dispatched for video: {$this->video->getAttribute('id')}");
    }
}
