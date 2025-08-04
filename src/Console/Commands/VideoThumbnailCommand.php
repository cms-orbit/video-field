<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Console\Commands;

use Illuminate\Console\Command;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoThumbnailJob;
use Exception;

class VideoThumbnailCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video:thumbnail {video?} {--time=5} {--force} {--all}';

    /**
     * The console command description.
     */
    protected $description = 'Generate thumbnails for videos using FFmpeg';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $videoId = $this->argument('video');
        $captureTime = (int) $this->option('time');
        $force = $this->option('force');
        $all = $this->option('all');

        if ($videoId) {
            $video = Video::findOrFail($videoId);
            return $this->dispatchThumbnailJob($video, $captureTime, $force) ? 0 : 1;
        }

        $query = Video::query();
        if (!$all) {
            $query->where('status', 'completed')
                  ->whereNull('thumbnail_path');
        }

        $videos = $query->get();
        $this->info("Found {$videos->count()} videos to process");

        $successCount = 0;
        foreach ($videos as $video) {
            if ($this->dispatchThumbnailJob($video, $captureTime, $force)) {
                $successCount++;
            }
        }

        $this->info("Successfully dispatched thumbnail jobs for {$successCount}/{$videos->count()} videos");
        return 0;
    }

    /**
     * Dispatch thumbnail generation job.
     */
    private function dispatchThumbnailJob(Video $video, int $captureTime, bool $force): bool
    {
        try {
            $this->info("Dispatching thumbnail job for: {$video->getAttribute('title')} (ID: {$video->getAttribute('id')})");

            // Create and dispatch the job
            $job = new VideoThumbnailJob($video, $captureTime, $force);
            dispatch($job);

            $this->line("  ✅ Job dispatched to queue");
            return true;

        } catch (Exception $e) {
            $this->error("  ❌ Failed to dispatch job: " . $e->getMessage());
            return false;
        }
    }
}
