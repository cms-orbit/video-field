<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Services\AbrManifestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class VideoManifestJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 2;

    protected Video $video;

    /**
     * Create a new job instance.
     */
        public function __construct(Video $video)
    {
        $this->video = $video;
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
            Log::info("Starting manifest generation for video: {$this->video->getAttribute('id')}");

            $manifestService = new AbrManifestService();

            // Generate HLS manifest
            $hlsPath = $manifestService->generateHlsManifest($this->video);
            if ($hlsPath) {
                Log::info("HLS manifest generated: {$hlsPath}");
            }

            // Generate DASH manifest
            $dashPath = $manifestService->generateDashManifest($this->video);
            if ($dashPath) {
                Log::info("DASH manifest generated: {$dashPath}");
            }

            // Update ABR profiles cache
            $manifestService->updateAbrProfiles($this->video);

            Log::info("Manifest generation completed for video: {$this->video->getAttribute('id')}");

        } catch (Exception $e) {
            Log::error("Manifest generation job exception for video: {$this->video->getAttribute('id')}", [
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
        Log::error("Manifest generation job failed for video: {$this->video->getAttribute('id')}", [
            'error' => $exception->getMessage()
        ]);
    }
}