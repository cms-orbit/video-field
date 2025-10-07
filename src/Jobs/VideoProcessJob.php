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
        $videoId = $this->video->getAttribute('id');
        $queueName = config('orbit-video.channels.queue', 'encode_video');

        $this->logInfo("Setting up video processing job chain", [
            'video_id' => $videoId,
            'queue' => $queueName,
            'force' => $this->force,
            'has_model_profiles' => !empty($this->modelProfiles),
        ]);

        // Create jobs
        $jobs = [];
        $jobNames = [];
        
        $jobs[] = (new VideoEncodeJob($this->video, $this->modelProfiles, $this->force))
            ->onQueue($queueName);
        $jobNames[] = 'encode';

        $jobs[] = (new VideoThumbnailJob($this->video, null, $this->force))
            ->onQueue($queueName);
        $jobNames[] = 'thumbnail';

        // Add sprite job only if enabled in config
        $spriteEnabled = config('orbit-video.sprites.enabled', true);
        if ($spriteEnabled) {
            $jobs[] = (new VideoSpriteJob($this->video, null, null, null, $this->force))
                ->onQueue($queueName);
            $jobNames[] = 'sprite';
        } else {
            $this->logDebug("Sprite generation disabled in config", ['video_id' => $videoId]);
        }

        $jobs[] = (new VideoManifestJob($this->video))
            ->onQueue($queueName);
        $jobNames[] = 'manifest';

        $this->logInfo("Job chain prepared", [
            'video_id' => $videoId,
            'total_jobs' => count($jobs),
            'jobs' => $jobNames,
        ]);

        // Chain jobs using Bus::chain()
        \Illuminate\Support\Facades\Bus::chain($jobs)->onQueue($queueName)->dispatch();

        $this->logInfo("Job chain dispatched successfully", [
            'video_id' => $videoId,
            'queue' => $queueName,
            'jobs' => $jobNames,
        ]);
    }
}
