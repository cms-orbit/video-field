<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Exception;

class VideoProcessJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 7200; // 2 hours
    public $tries = 2;

    protected Video $video;
    protected bool $force;
    protected ?string $profileFilter;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, bool $force = false, ?string $profileFilter = null)
    {
        $this->video = $video;
        $this->force = $force;
        $this->profileFilter = $profileFilter;

        // Set queue configuration
        $this->onQueue(config('video.queue.queue_name', 'encode_video'));
        $this->onConnection(config('video.queue.connection', 'redis'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting complete video processing for video: {$this->video->getAttribute('id')}");

            // Update video status
            $this->video->update(['status' => 'processing']);

            // Chain jobs in sequence
            $this->processVideoSequentially();

            Log::info("Video processing job chain started for video: {$this->video->getAttribute('id')}");

        } catch (Exception $e) {
            $this->video->update(['status' => 'failed']);
            Log::error("Video processing job exception for video: {$this->video->getAttribute('id')}", [
                'error' => $e->getMessage(),
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

        Log::error("Video processing job failed for video: {$this->video->getAttribute('id')}", [
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Process video with job chaining.
     */
    private function processVideoSequentially(): void
    {
        $queueName = config('video.queue.queue_name', 'encode_video');

        // Create jobs
        $encodeJob = (new VideoEncodeJob($this->video, $this->profileFilter, $this->force))
            ->onQueue($queueName);

        $thumbnailJob = (new VideoThumbnailJob($this->video, 5, $this->force))
            ->onQueue($queueName);

        $spriteJob = (new VideoSpriteJob($this->video, 100, 10, 10, $this->force))
            ->onQueue($queueName);

        // Chain jobs using Bus::chain()
        \Illuminate\Support\Facades\Bus::chain([
            $encodeJob,
            $thumbnailJob,
            $spriteJob
        ])->onQueue($queueName)->dispatch();

        Log::info("Job chain dispatched for video: {$this->video->getAttribute('id')}");
    }
}
