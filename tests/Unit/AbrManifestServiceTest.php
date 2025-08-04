<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Tests\Unit;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;
use CmsOrbit\VideoField\Services\AbrManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AbrManifestServiceTest extends TestCase
{
    use RefreshDatabase;

    private AbrManifestService $manifestService;
    private Video $testVideo;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->manifestService = new AbrManifestService();
        
        // Create test video
        $this->testVideo = Video::create([
            'title' => 'Test ABR Video',
            'description' => 'A test video for ABR testing',
            'original_filename' => 'SampleVideo_1280x720_1mb.mp4',
            'original_size' => 1055736,
            'duration' => 30,
            'width' => 1280,
            'height' => 720,
            'framerate' => 30.0,
            'bitrate' => 1500000,
            'mime_type' => 'video/mp4',
            'status' => 'completed',
            'user_id' => 1,
        ]);

        // Create test profiles
        $profiles = [
            ['profile' => 'HD@30fps', 'width' => 1280, 'height' => 720, 'framerate' => 30],
            ['profile' => 'SD@30fps', 'width' => 640, 'height' => 480, 'framerate' => 30],
        ];

        foreach ($profiles as $profileData) {
            VideoProfile::create(array_merge($profileData, [
                'video_id' => $this->testVideo->getAttribute('id'),
                'encoded' => true,
                'file_size' => 1000000,
                'field' => 'default',
            ]));
        }

        // Mock storage
        Storage::fake('public');
    }

    public function test_hls_manifest_generation(): void
    {
        $manifestPath = $this->manifestService->generateHlsManifest($this->testVideo);

        $this->assertNotNull($manifestPath);
        $this->assertStringContainsString('playlist.m3u8', $manifestPath);
        
        // Check if manifest was saved
        Storage::disk('public')->assertExists($manifestPath);
        
        // Check manifest content
        $content = Storage::disk('public')->get($manifestPath);
        $this->assertStringContainsString('#EXTM3U', $content);
        $this->assertStringContainsString('#EXT-X-VERSION:3', $content);
        $this->assertStringContainsString('#EXT-X-STREAM-INF', $content);
        $this->assertStringContainsString('BANDWIDTH=', $content);
        $this->assertStringContainsString('RESOLUTION=', $content);

        // Check if video record was updated
        $this->testVideo->refresh();
        $this->assertEquals($manifestPath, $this->testVideo->getAttribute('hls_manifest_path'));
    }

    public function test_dash_manifest_generation(): void
    {
        $manifestPath = $this->manifestService->generateDashManifest($this->testVideo);

        $this->assertNotNull($manifestPath);
        $this->assertStringContainsString('manifest.mpd', $manifestPath);
        
        // Check if manifest was saved
        Storage::disk('public')->assertExists($manifestPath);
        
        // Check manifest content
        $content = Storage::disk('public')->get($manifestPath);
        $this->assertStringContainsString('<?xml version="1.0"', $content);
        $this->assertStringContainsString('<MPD xmlns=', $content);
        $this->assertStringContainsString('<Period>', $content);
        $this->assertStringContainsString('<AdaptationSet', $content);
        $this->assertStringContainsString('<Representation', $content);
        $this->assertStringContainsString('bandwidth=', $content);

        // Check if video record was updated
        $this->testVideo->refresh();
        $this->assertEquals($manifestPath, $this->testVideo->getAttribute('dash_manifest_path'));
    }

    public function test_abr_profiles_update(): void
    {
        $this->manifestService->updateAbrProfiles($this->testVideo);

        $this->testVideo->refresh();
        $abrProfiles = $this->testVideo->getAttribute('abr_profiles');

        $this->assertIsArray($abrProfiles);
        $this->assertArrayHasKey('HD@30fps', $abrProfiles);
        $this->assertArrayHasKey('SD@30fps', $abrProfiles);

        // Should have fallback for higher quality profiles
        $allProfiles = config('video.default_profiles', []);
        foreach ($allProfiles as $profileName => $config) {
            $this->assertArrayHasKey($profileName, $abrProfiles);
            $this->assertNotEmpty($abrProfiles[$profileName]);
        }
    }

    public function test_fallback_profile_selection(): void
    {
        // Only create lower quality profile
        $this->testVideo->profiles()->delete();
        VideoProfile::create([
            'video_id' => $this->testVideo->getAttribute('id'),
            'profile' => 'SD@30fps',
            'width' => 640,
            'height' => 480,
            'framerate' => 30,
            'encoded' => true,
            'file_size' => 500000,
            'field' => 'default',
        ]);

        $this->manifestService->updateAbrProfiles($this->testVideo);

        $this->testVideo->refresh();
        $abrProfiles = $this->testVideo->getAttribute('abr_profiles');

        // Higher quality profiles should fallback to SD
        $sdPath = $abrProfiles['SD@30fps'] ?? '';
        $this->assertNotEmpty($sdPath);

        // Higher quality profiles should use the same path
        if (isset($abrProfiles['HD@30fps'])) {
            $this->assertEquals($sdPath, $abrProfiles['HD@30fps']);
        }
        if (isset($abrProfiles['FHD@30fps'])) {
            $this->assertEquals($sdPath, $abrProfiles['FHD@30fps']);
        }
    }

    public function test_manifest_generation_with_no_profiles(): void
    {
        // Delete all profiles
        $this->testVideo->profiles()->delete();

        $hlsPath = $this->manifestService->generateHlsManifest($this->testVideo);
        $dashPath = $this->manifestService->generateDashManifest($this->testVideo);

        $this->assertNull($hlsPath);
        $this->assertNull($dashPath);
    }

    public function test_bandwidth_estimation(): void
    {
        // Test with different resolutions
        $profiles = [
            ['width' => 3840, 'height' => 2160], // 4K
            ['width' => 1920, 'height' => 1080], // 1080p
            ['width' => 1280, 'height' => 720],  // 720p
            ['width' => 640, 'height' => 480],   // 480p
        ];

        foreach ($profiles as $profile) {
            // Use reflection to test private method
            $reflection = new \ReflectionClass($this->manifestService);
            $method = $reflection->getMethod('estimateBandwidth');
            $method->setAccessible(true);

            $mockProfile = (object) $profile;
            $bandwidth = $method->invoke($this->manifestService, $mockProfile);

            $this->assertIsInt($bandwidth);
            $this->assertGreaterThan(0, $bandwidth);

            // Higher resolution should have higher bandwidth
            if ($profile['width'] >= 3840) {
                $this->assertGreaterThanOrEqual(8000000, $bandwidth); // 4K: 8Mbps+
            } elseif ($profile['width'] >= 1920) {
                $this->assertGreaterThanOrEqual(3000000, $bandwidth); // 1080p: 3Mbps+
            } elseif ($profile['width'] >= 1280) {
                $this->assertGreaterThanOrEqual(1500000, $bandwidth); // 720p: 1.5Mbps+
            }
        }
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