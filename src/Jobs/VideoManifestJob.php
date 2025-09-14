<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Jobs;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Services\AbrManifestService;
use CmsOrbit\VideoField\Traits\VideoJobTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class VideoManifestJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, VideoJobTrait;

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
            $videoId = $this->video->getAttribute('id');
            $this->logJobStart('manifest generation', $videoId);

            $manifestService = new AbrManifestService();

            // Check if any profiles have HLS or DASH enabled
            $hasHlsProfiles = $this->video->profiles()
                ->where('export_hls', true)
                ->where('encoded', true)
                ->exists();

            $hasDashProfiles = $this->video->profiles()
                ->where('export_dash', true)
                ->where('encoded', true)
                ->exists();

            // Generate HLS manifest only if there are HLS profiles
            if ($hasHlsProfiles) {
                $hlsPath = $manifestService->generateHlsManifest($this->video);
                if ($hlsPath) {
                    Log::info("HLS manifest generated: {$hlsPath}");
                }
            }

            // Generate DASH manifest only if there are DASH profiles
            if ($hasDashProfiles) {
                $dashPath = $manifestService->generateDashManifest($this->video);
                if ($dashPath) {
                    Log::info("DASH manifest generated: {$dashPath}");
                }
            }

            // Update ABR profiles cache only if there are streaming profiles
            if ($hasHlsProfiles || $hasDashProfiles) {
                $manifestService->updateAbrProfiles($this->video);
            }

            $this->logJobCompletion('manifest generation', $videoId);

            // If all profiles are encoded, mark video as completed
            if ($this->video->isFullyEncoded()) {
                $this->video->update(['status' => 'completed']);
            }

        } catch (Exception $e) {
            $this->logJobError('manifest generation', $this->video->getAttribute('id'), $e->getMessage(), [
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