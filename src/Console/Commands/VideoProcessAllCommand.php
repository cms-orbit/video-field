<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Console\Commands;

use Illuminate\Console\Command;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoProcessJob;

class VideoProcessAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video:process-all {video?} {--force}';

    /**
     * The console command description.
     */
    protected $description = 'Process video with encoding, thumbnail, and sprite generation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $videoId = $this->argument('video');
        $force = $this->option('force');

        if ($videoId) {
            $video = Video::findOrFail($videoId);
            return $this->dispatchProcessJob($video, $force) ? 0 : 1;
        }

        $videos = Video::whereIn('status', ['pending', 'failed'])->get();
        $this->info("Found {$videos->count()} videos to process");

        $successCount = 0;
        foreach ($videos as $video) {
            if ($this->dispatchProcessJob($video, $force)) {
                $successCount++;
            }
        }

        $this->info("Successfully dispatched processing jobs for {$successCount}/{$videos->count()} videos");
        return 0;
    }

    /**
     * Dispatch complete video processing job.
     */
    private function dispatchProcessJob(Video $video, bool $force): bool
    {
        try {
            $this->info("=== Dispatching complete processing for: {$video->getAttribute('title')} (ID: {$video->getAttribute('id')}) ===");

            // Create and dispatch the job
            $job = new VideoProcessJob($video, $force);
            dispatch($job);

            $this->line("🎉 Job chain dispatched to queue!");
            $this->line("The following will be processed sequentially:");
            $this->line("  🎬 Step 1: Video encoding");
            $this->line("  📸 Step 2: Thumbnail generation");
            $this->line("  🎭 Step 3: Sprite sheet generation");
            $this->line("");

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Failed to dispatch processing job: " . $e->getMessage());
            return false;
        }
    }
}
