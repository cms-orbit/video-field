<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Tests\Unit;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Jobs\VideoEncodeJob;
use CmsOrbit\VideoField\Jobs\VideoThumbnailJob;
use CmsOrbit\VideoField\Jobs\VideoSpriteJob;
use CmsOrbit\VideoField\Jobs\VideoManifestJob;
use CmsOrbit\VideoField\Jobs\VideoProcessJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class VideoJobsTest extends TestCase
{
    use RefreshDatabase;

    private Video $testVideo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test video
        $this->testVideo = Video::create([
            'title' => 'Test Job Video',
            'description' => 'A test video for job testing',
            'original_filename' => 'SampleVideo_1280x720_1mb.mp4',
            'original_size' => 1055736,
            'duration' => 30,
            'width' => 1280,
            'height' => 720,
            'framerate' => 30.0,
            'bitrate' => 1500000,
            'mime_type' => 'video/mp4',
            'status' => 'pending',
            'user_id' => 1,
        ]);

        // Mock storage and queues
        Storage::fake('public');
        Queue::fake();
        Bus::fake();
    }

    public function test_video_encode_job_dispatch(): void
    {
        $job = new VideoEncodeJob($this->testVideo);
        
        // Test job creation and properties
        $this->assertInstanceOf(VideoEncodeJob::class, $job);
        $this->assertEquals($this->testVideo->getAttribute('id'), $job->getVideo()->getAttribute('id'));
        
        // Test job can be dispatched (without actually running it)
        $this->assertTrue(method_exists($job, 'handle'));
    }

    public function test_video_thumbnail_job_dispatch(): void
    {
        $job = new VideoThumbnailJob($this->testVideo, 5);
        
        // Test job creation and properties
        $this->assertInstanceOf(VideoThumbnailJob::class, $job);
        $this->assertEquals($this->testVideo->getAttribute('id'), $job->getVideo()->getAttribute('id'));
        
        // Test job can be dispatched (without actually running it)
        $this->assertTrue(method_exists($job, 'handle'));
    }

    public function test_video_sprite_job_dispatch(): void
    {
        $job = new VideoSpriteJob($this->testVideo, 100, 10, 10);
        
        // Test job creation and properties
        $this->assertInstanceOf(VideoSpriteJob::class, $job);
        $this->assertEquals($this->testVideo->getAttribute('id'), $job->getVideo()->getAttribute('id'));
        
        // Test job can be dispatched (without actually running it)
        $this->assertTrue(method_exists($job, 'handle'));
    }

    public function test_video_manifest_job_dispatch(): void
    {
        $job = new VideoManifestJob($this->testVideo);
        
        // Test job creation and properties
        $this->assertInstanceOf(VideoManifestJob::class, $job);
        $this->assertEquals($this->testVideo->getAttribute('id'), $job->getVideo()->getAttribute('id'));
        
        // Test job can be dispatched (without actually running it)
        $this->assertTrue(method_exists($job, 'handle'));
    }

    public function test_video_process_job_chain(): void
    {
        $job = new VideoProcessJob($this->testVideo);
        
        // Test job creation and properties
        $this->assertInstanceOf(VideoProcessJob::class, $job);
        $this->assertEquals($this->testVideo->getAttribute('id'), $job->getVideo()->getAttribute('id'));
        
        // Test job can be dispatched (without actually running it)
        $this->assertTrue(method_exists($job, 'handle'));
    }

    public function test_job_chain_sequence(): void
    {
        $processJob = new VideoProcessJob($this->testVideo);
        
        // Execute the job to trigger the chain
        $processJob->handle();

        // Should have dispatched a chain of jobs
        Bus::assertChained([
            VideoEncodeJob::class,
            VideoThumbnailJob::class,
            VideoSpriteJob::class,
            VideoManifestJob::class,
        ]);
    }

    public function test_job_queue_configuration(): void
    {
        $encodeJob = new VideoEncodeJob($this->testVideo);
        $thumbnailJob = new VideoThumbnailJob($this->testVideo);
        $spriteJob = new VideoSpriteJob($this->testVideo);
        $manifestJob = new VideoManifestJob($this->testVideo);

        // Test that jobs can be configured with queue settings
        $expectedQueue = config('video.queue.queue_name', 'encode_video');

        $encodeJob->onQueue($expectedQueue);
        $thumbnailJob->onQueue($expectedQueue);
        $spriteJob->onQueue($expectedQueue);
        $manifestJob->onQueue($expectedQueue);

        $this->assertEquals($expectedQueue, $encodeJob->queue);
        $this->assertEquals($expectedQueue, $thumbnailJob->queue);
        $this->assertEquals($expectedQueue, $spriteJob->queue);
        $this->assertEquals($expectedQueue, $manifestJob->queue);
    }

    public function test_job_timeout_configuration(): void
    {
        $encodeJob = new VideoEncodeJob($this->testVideo);
        $thumbnailJob = new VideoThumbnailJob($this->testVideo);
        $spriteJob = new VideoSpriteJob($this->testVideo);
        $manifestJob = new VideoManifestJob($this->testVideo);

        // Check timeout values
        $this->assertEquals(3600, $encodeJob->timeout); // 1 hour for encoding
        $this->assertEquals(300, $thumbnailJob->timeout); // 5 minutes for thumbnail
        $this->assertEquals(600, $spriteJob->timeout); // 10 minutes for sprite
        $this->assertEquals(300, $manifestJob->timeout); // 5 minutes for manifest
    }

    public function test_job_retry_configuration(): void
    {
        $encodeJob = new VideoEncodeJob($this->testVideo);
        $thumbnailJob = new VideoThumbnailJob($this->testVideo);
        $spriteJob = new VideoSpriteJob($this->testVideo);
        $manifestJob = new VideoManifestJob($this->testVideo);

        // Check retry counts
        $this->assertEquals(3, $encodeJob->tries);
        $this->assertEquals(2, $thumbnailJob->tries);
        $this->assertEquals(2, $spriteJob->tries);
        $this->assertEquals(2, $manifestJob->tries);
    }

    public function test_job_serialization(): void
    {
        $encodeJob = new VideoEncodeJob($this->testVideo, 'HD@30fps', true);
        
        // Test serialization and deserialization
        $serialized = serialize($encodeJob);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(VideoEncodeJob::class, $unserialized);
        $this->assertEquals($this->testVideo->getAttribute('id'), $unserialized->getVideo()->getAttribute('id'));
    }

    public function test_failed_job_handling(): void
    {
        $job = new VideoEncodeJob($this->testVideo);
        
        // Simulate job failure
        $exception = new \Exception('Test exception');
        $job->failed($exception);

        // Video status should be updated to failed
        $this->testVideo->refresh();
        $this->assertEquals('failed', $this->testVideo->getAttribute('status'));
    }

    protected function tearDown(): void
    {
        // Clean up test video
        if (isset($this->testVideo)) {
            $this->testVideo->profiles()->delete();
            $this->testVideo->delete();
        }

        parent::tearDown();
    }
}