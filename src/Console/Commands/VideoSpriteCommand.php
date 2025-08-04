<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Console\Commands;

use Illuminate\Console\Command;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoSpriteJob;
use Exception;

class VideoSpriteCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video:sprite {video?} {--frames=100} {--columns=10} {--rows=10} {--force} {--all}';

    /**
     * The console command description.
     */
    protected $description = 'Generate sprite sheets for video scrubbing using FFmpeg';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $videoId = $this->argument('video');
        $frames = (int) $this->option('frames');
        $columns = (int) $this->option('columns');
        $rows = (int) $this->option('rows');
        $force = $this->option('force');
        $all = $this->option('all');

        // Validate options
        if ($frames <= 0) $frames = 100;
        if ($columns <= 0) $columns = 10;
        if ($rows <= 0) $rows = 10;

        if ($videoId) {
            $video = Video::findOrFail($videoId);
            return $this->dispatchSpriteJob($video, $frames, $columns, $rows, $force) ? 0 : 1;
        }

        $query = Video::query();
        if (!$all) {
            $query->where('status', 'completed')
                  ->whereNull('scrubbing_sprite_path');
        }

        $videos = $query->get();
        $this->info("Found {$videos->count()} videos to process");

        $successCount = 0;
        foreach ($videos as $video) {
            if ($this->dispatchSpriteJob($video, $frames, $columns, $rows, $force)) {
                $successCount++;
            }
        }

        $this->info("Successfully dispatched sprite jobs for {$successCount}/{$videos->count()} videos");
        return 0;
    }

    /**
     * Dispatch sprite generation job.
     */
    private function dispatchSpriteJob(Video $video, int $frames, int $columns, int $rows, bool $force): bool
    {
        try {
            $this->info("Dispatching sprite job for: {$video->getAttribute('title')} (ID: {$video->getAttribute('id')})");

            // Create and dispatch the job
            $job = new VideoSpriteJob($video, $frames, $columns, $rows, $force);
            dispatch($job);

            $this->line("  ✅ Job dispatched to queue");
            return true;

        } catch (Exception $e) {
            $this->error("  ❌ Failed to dispatch job: " . $e->getMessage());
            return false;
        }
    }
}
