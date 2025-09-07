<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Console\Commands;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoProcessJob;
use Illuminate\Console\Command;

class VideoEncodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video:encode {video_id? : The ID of the video to encode} {--force : Force re-encoding even if already encoded}';

    /**
     * The console command description.
     */
    protected $description = 'Encode a video with all available profiles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $videoId = $this->argument('video_id');
        $force = $this->option('force');

        if ($videoId) {
            // Encode specific video
            $video = Video::find($videoId);
            if (!$video) {
                $this->error("Video with ID {$videoId} not found.");
                return 1;
            }

            $this->info("Starting encoding for video: {$video->getAttribute('id')}");
            VideoProcessJob::dispatch($video, $force);
            $this->info("Video encoding job dispatched successfully.");
        } else {
            // Encode all pending videos
            $videos = Video::where('status', 'pending')->get();
            
            if ($videos->isEmpty()) {
                $this->info('No pending videos found.');
                return 0;
            }

            $this->info("Found {$videos->count()} pending videos. Starting encoding...");
            
            foreach ($videos as $video) {
                $this->info("Dispatching encoding job for video: {$video->getAttribute('id')}");
                VideoProcessJob::dispatch($video, $force);
            }
            
            $this->info("All video encoding jobs dispatched successfully.");
        }

        return 0;
    }
}
