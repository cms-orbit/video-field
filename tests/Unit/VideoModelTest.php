<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Tests\Unit;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoModelTest extends TestCase
{
    use RefreshDatabase;

    private Video $testVideo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test video
        $this->testVideo = Video::create([
            'title' => 'Test Video',
            'description' => 'A test video for unit testing',
            'original_filename' => 'SampleVideo_1280x720_1mb.mp4',
            'original_size' => 1055736,
            'duration' => 30,
            'original_width' => 1280,
            'original_height' => 720,
            'original_framerate' => 30.0,
            'original_bitrate' => 1500000,
            'mime_type' => 'video/mp4',
            'status' => 'completed',
            'user_id' => 1,
        ]);
    }

    public function test_video_creation(): void
    {
        $this->assertInstanceOf(Video::class, $this->testVideo);
        $this->assertEquals('Test Video', $this->testVideo->getAttribute('title'));
        $this->assertEquals(1280, $this->testVideo->getAttribute('original_width'));
        $this->assertEquals(720, $this->testVideo->getAttribute('original_height'));
    }

    public function test_video_path_generation(): void
    {
        $videoPath = $this->testVideo->getVideoPath();
        $thumbnailPath = $this->testVideo->getThumbnailPath();
        $spritePath = $this->testVideo->getSpritePath();

        $this->assertStringContainsString((string)$this->testVideo->getAttribute('id'), $videoPath);
        $this->assertStringContainsString((string)$this->testVideo->getAttribute('id'), $thumbnailPath);
        $this->assertStringContainsString((string)$this->testVideo->getAttribute('id'), $spritePath);
    }

    public function test_video_profiles_relationship(): void
    {
        // Create test profiles
        $profiles = [
            ['profile' => 'HD@30fps', 'width' => 1280, 'height' => 720, 'framerate' => 30, 'status' => 'completed'],
            ['profile' => 'SD@30fps', 'width' => 640, 'height' => 480, 'framerate' => 30, 'status' => 'completed'],
        ];

        foreach ($profiles as $profileData) {
            VideoProfile::create(array_merge($profileData, [
                'video_id' => $this->testVideo->getAttribute('id'),
                'file_size' => 1000000,
                'field' => 'default',
                'encoded' => true,
            ]));
        }

        $this->assertEquals(2, $this->testVideo->profiles()->count());
        
        $hdProfile = $this->testVideo->profiles()
            ->where('profile', 'HD@30fps')
            ->first();
            
        $this->assertNotNull($hdProfile);
        $this->assertEquals(1280, $hdProfile->getAttribute('width'));
    }

    public function test_abr_profiles_fallback(): void
    {
        // Create profiles (only HD available, no 4K)
        VideoProfile::create([
            'video_id' => $this->testVideo->getAttribute('id'),
            'profile' => 'HD@30fps',
            'width' => 1280,
            'height' => 720,
            'framerate' => 30,
            'encoded' => true,
            'file_size' => 1000000,
            'field' => 'default',
        ]);

        // Mock ABR profiles with fallback
        $abrProfiles = [
            'HD@30fps' => 'videos/1/HD@30fps.mp4',
            'FHD@30fps' => 'videos/1/HD@30fps.mp4', // Fallback to HD
            '4K@60fps' => 'videos/1/HD@30fps.mp4',  // Fallback to HD
        ];

        $this->testVideo->update(['abr_profiles' => $abrProfiles]);

        $availableProfiles = $this->testVideo->getAvailableProfiles();
        
        $this->assertArrayHasKey('HD@30fps', $availableProfiles);
        $this->assertArrayHasKey('FHD@30fps', $availableProfiles);
        $this->assertArrayHasKey('4K@60fps', $availableProfiles);
        
        // All should point to HD profile path
        $this->assertEquals($availableProfiles['HD@30fps'], $availableProfiles['FHD@30fps']);
        $this->assertEquals($availableProfiles['HD@30fps'], $availableProfiles['4K@60fps']);
    }

    public function test_video_supports_abr(): void
    {
        // No profiles - should not support ABR
        $this->assertFalse($this->testVideo->supportsAbr());

        // Single profile - should not support ABR
        $this->testVideo->update(['abr_profiles' => ['HD@30fps' => 'path1.mp4']]);
        $this->assertFalse($this->testVideo->supportsAbr());

        // Multiple profiles - should support ABR
        $this->testVideo->update(['abr_profiles' => [
            'HD@30fps' => 'path1.mp4',
            'SD@30fps' => 'path2.mp4'
        ]]);
        $this->assertTrue($this->testVideo->supportsAbr());
    }

    public function test_player_metadata_generation(): void
    {
        // Set up complete video data
        $this->testVideo->update([
            'thumbnail_path' => 'videos/1/thumbnails/thumbnail.jpeg',
            'scrubbing_sprite_path' => 'videos/1/sprites/sprite.jpeg',
            'sprite_columns' => 10,
            'sprite_rows' => 10,
            'sprite_interval' => 3,
            'hls_manifest_path' => 'videos/1/playlist.m3u8',
            'dash_manifest_path' => 'videos/1/manifest.mpd',
            'abr_profiles' => [
                'HD@30fps' => 'videos/1/HD@30fps.mp4',
                'SD@30fps' => 'videos/1/SD@30fps.mp4'
            ]
        ]);

        $metadata = $this->testVideo->getPlayerMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('id', $metadata);
        $this->assertArrayHasKey('title', $metadata);
        $this->assertArrayHasKey('duration', $metadata);
        $this->assertArrayHasKey('thumbnail', $metadata);
        $this->assertArrayHasKey('sprite', $metadata);
        $this->assertArrayHasKey('hls', $metadata);
        $this->assertArrayHasKey('dash', $metadata);
        $this->assertArrayHasKey('profiles', $metadata);
        $this->assertArrayHasKey('supportsAbr', $metadata);

        $this->assertEquals($this->testVideo->getAttribute('id'), $metadata['id']);
        $this->assertEquals($this->testVideo->getAttribute('title'), $metadata['title']);
        $this->assertTrue($metadata['supportsAbr']);
    }

    public function test_sprite_metadata(): void
    {
        // Mock storage to simulate sprite file exists
        Storage::fake('public');
        Storage::disk('public')->put('videos/1/sprites/sprite.jpeg', 'fake sprite data');
        
        $this->testVideo->update([
            'scrubbing_sprite_path' => 'videos/1/sprites/sprite.jpeg',
            'sprite_columns' => 10,
            'sprite_rows' => 10,
            'sprite_interval' => 3,
        ]);

        $spriteData = $this->testVideo->getSpriteMetadata();

        $this->assertIsArray($spriteData);
        $this->assertArrayHasKey('url', $spriteData);
        $this->assertArrayHasKey('columns', $spriteData);
        $this->assertArrayHasKey('rows', $spriteData);
        $this->assertArrayHasKey('interval', $spriteData);
        $this->assertArrayHasKey('total_frames', $spriteData);

        $this->assertEquals(10, $spriteData['columns']);
        $this->assertEquals(10, $spriteData['rows']);
        $this->assertEquals(3, $spriteData['interval']);
        $this->assertEquals(100, $spriteData['total_frames']);
    }

    public function test_video_url_with_profile_fallback(): void
    {
        // Create only one profile
        VideoProfile::create([
            'video_id' => $this->testVideo->getAttribute('id'),
            'profile' => 'HD@30fps',
            'width' => 1280,
            'height' => 720,
            'framerate' => 30,
            'encoded' => true,
            'file_size' => 1000000,
            'field' => 'default',
        ]);

        // Mock ABR profiles
        $this->testVideo->update(['abr_profiles' => [
            'HD@30fps' => 'videos/1/HD@30fps.mp4',
            'FHD@30fps' => 'videos/1/HD@30fps.mp4', // Fallback
        ]]);

        // Request existing profile
        $hdUrl = $this->testVideo->getUrl('HD@30fps');
        $this->assertStringContainsString('HD@30fps.mp4', $hdUrl);

        // Request non-existing profile (should get fallback)
        $fhdUrl = $this->testVideo->getUrl('FHD@30fps');
        $this->assertStringContainsString('HD@30fps.mp4', $fhdUrl);

        // Request non-existing profile not in ABR (should get default)
        $defaultUrl = $this->testVideo->getUrl('4K@60fps');
        $this->assertNotNull($defaultUrl);
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