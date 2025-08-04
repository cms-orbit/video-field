<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Console\Commands;

use Illuminate\Console\Command;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoEncodeJob;
use Exception;

class VideoEncodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video:encode {video?} {--profile=} {--force} {--all}';

    /**
     * The console command description.
     */
    protected $description = 'Encode video files with specified profiles using FFmpeg';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $videoId = $this->argument('video');
        $profileFilter = $this->option('profile');
        $force = $this->option('force');
        $all = $this->option('all');

        if ($videoId) {
            $video = Video::findOrFail($videoId);
            return $this->dispatchEncodeJob($video, $profileFilter, $force) ? 0 : 1;
        }

        $query = Video::query();
        if (!$all) {
            $query->whereIn('status', ['pending', 'failed']);
        }

        $videos = $query->get();
        $this->info("Found {$videos->count()} videos to process");

        $successCount = 0;
        foreach ($videos as $video) {
            if ($this->dispatchEncodeJob($video, $profileFilter, $force)) {
                $successCount++;
            }
        }

        $this->info("Successfully dispatched encoding jobs for {$successCount}/{$videos->count()} videos");
        return 0;
    }

    /**
     * Dispatch video encoding job.
     */
    private function dispatchEncodeJob(Video $video, ?string $profileFilter, bool $force): bool
    {
        try {
            $this->info("Dispatching encoding job for: {$video->getAttribute('title')} (ID: {$video->getAttribute('id')})");

            // Create and dispatch the job
            $job = new VideoEncodeJob($video, $profileFilter, $force);
            dispatch($job);

            $this->line("  ✅ Job dispatched to queue");
            return true;

        } catch (Exception $e) {
            $this->error("  ❌ Failed to dispatch job: " . $e->getMessage());
            return false;
        }
    }
}
