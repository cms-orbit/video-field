<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Services;

use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AbrManifestService
{
    /**
     * Generate HLS manifest (m3u8) for a video.
     */
    public function generateHlsManifest(Video $video): ?string
    {
        try {
            // Check if HLS export is enabled in config
            if (!config('orbit-video.default_encoding.export_hls', true)) {
                return null;
            }

            $profiles = $video->profiles()
                ->where('encoded', true)
                ->whereNotNull('hls_path')
                ->orderBy('width', 'desc')
                ->get();

            if ($profiles->isEmpty()) {
                return null;
            }

            $manifestContent = $this->buildHlsManifest($profiles);
            $videoDir = $this->getVideoDirectory($video);
            $manifestPath = $videoDir . '/playlist.m3u8';

            $disk = config('orbit-video.storage.disk');
            Storage::disk($disk)->put($manifestPath, $manifestContent);

            // Update video record
            $video->update(['hls_manifest_path' => $manifestPath]);

            Log::info("HLS manifest generated for video: {$video->getAttribute('id')}");
            return $manifestPath;

        } catch (\Exception $e) {
            Log::error("Failed to generate HLS manifest for video: {$video->getAttribute('id')}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Generate DASH ABR manifest (mpd) for a video.
     * Parses FFmpeg-generated manifests and combines them into ABR manifest.
     */
    public function generateDashManifest(Video $video): ?string
    {
        try {
            // Check if DASH export is enabled in config
            if (!config('orbit-video.default_encoding.export_dash', true)) {
                return null;
            }

            $profiles = $video->profiles()
                ->where('encoded', true)
                ->whereNotNull('dash_path')
                ->orderBy('width', 'desc')
                ->get();

            if ($profiles->isEmpty()) {
                return null;
            }

            $disk = config('orbit-video.storage.disk');
            
            // Parse FFmpeg-generated manifests and build ABR manifest
            $manifestContent = $this->buildAbrDashManifest($video, $profiles, $disk);
            
            if (!$manifestContent) {
                Log::error("Failed to build ABR DASH manifest for video: {$video->getAttribute('id')}");
                return null;
            }

            $videoDir = $this->getVideoDirectory($video);
            $manifestPath = $videoDir . '/manifest.mpd';

            Storage::disk($disk)->put($manifestPath, $manifestContent);

            // Update video record
            $video->update(['dash_manifest_path' => $manifestPath]);

            Log::info("ABR DASH manifest generated for video: {$video->getAttribute('id')} with {$profiles->count()} profiles");
            return $manifestPath;

        } catch (\Exception $e) {
            Log::error("Failed to generate DASH manifest for video: {$video->getAttribute('id')}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Build HLS manifest content.
     */
    private function buildHlsManifest($profiles): string
    {
        $content = "#EXTM3U\n";
        $content .= "#EXT-X-VERSION:3\n\n";

        foreach ($profiles as $profile) {
            $bandwidth = $this->estimateBandwidth($profile);
            $resolution = "{$profile->getAttribute('width')}x{$profile->getAttribute('height')}";
            $framerate = $profile->getAttribute('framerate') ?? 30;

            $content .= "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$resolution}\n";
            // Use HLS playlist path
            $hlsPath = $profile->getAttribute('hls_path');
            if ($hlsPath) {
                $relativePath = 'hls/' . basename(dirname($hlsPath)) . '/playlist.m3u8';
                $content .= $relativePath . "\n";
            }
        }

        return $content;
    }

    /**
     * Build ABR DASH manifest by parsing FFmpeg-generated manifests.
     */
    private function buildAbrDashManifest(Video $video, $profiles, $disk): ?string
    {
        try {
            $duration = $video->getAttribute('duration') ?? 0;
            
            // Parse first profile's manifest to get audio and basic structure
            $firstProfile = $profiles->first();
            $firstManifestPath = dirname($firstProfile->getAttribute('dash_path')) . '/manifest.mpd';
            
            if (!Storage::disk($disk)->exists($firstManifestPath)) {
                Log::error("First profile manifest not found: {$firstManifestPath}");
                return null;
            }
            
            $firstManifestXml = Storage::disk($disk)->get($firstManifestPath);
            $firstXml = simplexml_load_string($firstManifestXml);
            
            if (!$firstXml) {
                Log::error("Failed to parse first profile manifest");
                return null;
            }
            
            // Build ABR manifest
            $content = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
            $content .= '<MPD xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
            $content .= "\txmlns=\"urn:mpeg:dash:schema:mpd:2011\"" . "\n";
            $content .= "\txmlns:xlink=\"http://www.w3.org/1999/xlink\"" . "\n";
            $content .= "\txsi:schemaLocation=\"urn:mpeg:DASH:schema:MPD:2011 http://standards.iso.org/ittf/PubliclyAvailableStandards/MPEG-DASH_schema_files/DASH-MPD.xsd\"" . "\n";
            $content .= "\tprofiles=\"urn:mpeg:dash:profile:isoff-live:2011\"" . "\n";
            $content .= "\ttype=\"static\"" . "\n";
            $content .= "\tmediaPresentationDuration=\"PT{$duration}S\"" . "\n";
            $content .= "\tmaxSegmentDuration=\"PT10.0S\"" . "\n";
            $content .= "\tminBufferTime=\"PT33.3S\">" . "\n";
            $content .= "\t<ProgramInformation>" . "\n";
            $content .= "\t</ProgramInformation>" . "\n";
            $content .= "\t<ServiceDescription id=\"0\">" . "\n";
            $content .= "\t</ServiceDescription>" . "\n";
            $content .= "\t<Period id=\"0\" start=\"PT0.0S\">" . "\n";
            
            // Video AdaptationSet with all profiles
            $firstXml->registerXPathNamespace('mpd', 'urn:mpeg:dash:schema:mpd:2011');
            $videoAdaptation = $firstXml->xpath('//mpd:AdaptationSet[@contentType="video"]')[0] ?? null;
            
            if ($videoAdaptation) {
                $content .= "\t\t<AdaptationSet id=\"0\" contentType=\"video\" startWithSAP=\"1\" segmentAlignment=\"true\" bitstreamSwitching=\"true\" frameRate=\"{$videoAdaptation['frameRate']}\" maxWidth=\"{$videoAdaptation['maxWidth']}\" maxHeight=\"{$videoAdaptation['maxHeight']}\" par=\"{$videoAdaptation['par']}\" lang=\"und\">" . "\n";
                
                // Add all profile video representations
                $repId = 0;
                foreach ($profiles as $profile) {
                    $profileManifestPath = dirname($profile->getAttribute('dash_path')) . '/manifest.mpd';
                    
                    if (!Storage::disk($disk)->exists($profileManifestPath)) {
                        continue;
                    }
                    
                    $profileManifestXml = Storage::disk($disk)->get($profileManifestPath);
                    $profileXml = simplexml_load_string($profileManifestXml);
                    $profileXml->registerXPathNamespace('mpd', 'urn:mpeg:dash:schema:mpd:2011');
                    
                    $videoRep = $profileXml->xpath('//mpd:AdaptationSet[@contentType="video"]//mpd:Representation')[0] ?? null;
                    
                    if ($videoRep) {
                        $profileName = $profile->getAttribute('profile');
                        $profileDir = basename(dirname($profile->getAttribute('dash_path')));
                        
                        $content .= "\t\t\t<Representation id=\"{$repId}\" mimeType=\"{$videoRep['mimeType']}\" codecs=\"{$videoRep['codecs']}\" bandwidth=\"{$videoRep['bandwidth']}\" width=\"{$videoRep['width']}\" height=\"{$videoRep['height']}\" sar=\"{$videoRep['sar']}\">" . "\n";
                        
                        $segmentTemplate = $videoRep->SegmentTemplate;
                        if ($segmentTemplate) {
                            // 항상 stream0 사용 (각 프로파일 디렉토리 안에서는 모두 stream0)
                            $content .= "\t\t\t\t<SegmentTemplate timescale=\"{$segmentTemplate['timescale']}\" initialization=\"dash/{$profileDir}/init-stream0.m4s\" media=\"dash/{$profileDir}/chunk-stream0-\$Number%05d\$.m4s\" startNumber=\"{$segmentTemplate['startNumber']}\">" . "\n";
                            $content .= "\t\t\t\t\t<SegmentTimeline>" . "\n";
                            
                            foreach ($segmentTemplate->SegmentTimeline->S as $s) {
                                $content .= "\t\t\t\t\t\t<S";
                                if (isset($s['t'])) $content .= " t=\"{$s['t']}\"";
                                if (isset($s['d'])) $content .= " d=\"{$s['d']}\"";
                                if (isset($s['r'])) $content .= " r=\"{$s['r']}\"";
                                $content .= " />" . "\n";
                            }
                            
                            $content .= "\t\t\t\t\t</SegmentTimeline>" . "\n";
                            $content .= "\t\t\t\t</SegmentTemplate>" . "\n";
                        }
                        
                        $content .= "\t\t\t</Representation>" . "\n";
                    }
                    
                    $repId++;
                }
                
                $content .= "\t\t</AdaptationSet>" . "\n";
            }
            
            // Audio AdaptationSet from first profile
            $audioAdaptation = $firstXml->xpath('//mpd:AdaptationSet[@contentType="audio"]')[0] ?? null;
            
            if ($audioAdaptation) {
                $content .= "\t\t<AdaptationSet id=\"1\" contentType=\"audio\" startWithSAP=\"1\" segmentAlignment=\"true\" bitstreamSwitching=\"true\" lang=\"und\">" . "\n";
                
                $audioRep = $audioAdaptation->Representation;
                if ($audioRep) {
                    $profileDir = basename(dirname($firstProfile->getAttribute('dash_path')));
                    
                    $content .= "\t\t\t<Representation id=\"1\" mimeType=\"{$audioRep['mimeType']}\" codecs=\"{$audioRep['codecs']}\" bandwidth=\"{$audioRep['bandwidth']}\" audioSamplingRate=\"{$audioRep['audioSamplingRate']}\">" . "\n";
                    $content .= "\t\t\t\t<AudioChannelConfiguration schemeIdUri=\"urn:mpeg:dash:23003:3:audio_channel_configuration:2011\" value=\"2\" />" . "\n";
                    
                    $segmentTemplate = $audioRep->SegmentTemplate;
                    if ($segmentTemplate) {
                        $content .= "\t\t\t\t<SegmentTemplate timescale=\"{$segmentTemplate['timescale']}\" initialization=\"dash/{$profileDir}/init-stream1.m4s\" media=\"dash/{$profileDir}/chunk-stream1-\$Number%05d\$.m4s\" startNumber=\"{$segmentTemplate['startNumber']}\">" . "\n";
                        $content .= "\t\t\t\t\t<SegmentTimeline>" . "\n";
                        
                        foreach ($segmentTemplate->SegmentTimeline->S as $s) {
                            $content .= "\t\t\t\t\t\t<S";
                            if (isset($s['t'])) $content .= " t=\"{$s['t']}\"";
                            if (isset($s['d'])) $content .= " d=\"{$s['d']}\"";
                            if (isset($s['r'])) $content .= " r=\"{$s['r']}\"";
                            $content .= " />" . "\n";
                        }
                        
                        $content .= "\t\t\t\t\t</SegmentTimeline>" . "\n";
                        $content .= "\t\t\t\t</SegmentTemplate>" . "\n";
                    }
                    
                    $content .= "\t\t\t</Representation>" . "\n";
                }
                
                $content .= "\t\t</AdaptationSet>" . "\n";
            }
            
            $content .= "\t</Period>" . "\n";
            $content .= "</MPD>" . "\n";
            
            return $content;
            
        } catch (\Exception $e) {
            Log::error("Failed to build ABR DASH manifest", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Build DASH manifest content (legacy, kept for reference).
     */
    private function buildDashManifest(Video $video, $profiles): string
    {
        $duration = $video->getAttribute('duration') ?? 0;
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content .= '<MPD xmlns="urn:mpeg:dash:schema:mpd:2011" ' .
                   'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
                   'type="static" ' .
                   'mediaPresentationDuration="PT' . $duration . 'S" ' .
                   'profiles="urn:mpeg:dash:profile:isoff-main:2011" ' .
                   'minBufferTime="PT2S">' . "\n";

        $content .= '  <Period>' . "\n";
        
        // Video AdaptationSet
        $content .= '    <AdaptationSet mimeType="video/mp4" segmentAlignment="true" startWithSAP="1">' . "\n";

        foreach ($profiles as $profile) {
            $bandwidth = $this->estimateBandwidth($profile);
            $width = $profile->getAttribute('width');
            $height = $profile->getAttribute('height');
            $framerate = $profile->getAttribute('framerate') ?? 30;

            $content .= '      <Representation ' .
                       'id="' . $profile->getAttribute('profile') . '" ' .
                       'bandwidth="' . $bandwidth . '" ' .
                       'width="' . $width . '" ' .
                       'height="' . $height . '" ' .
                       'frameRate="' . $framerate . '" ' .
                       'codecs="avc1.640028">' . "\n";
            
            // DASH에서는 각 프로필의 세그먼트 파일들을 직접 참조
            $dashPath = $profile->getAttribute('dash_path');
            if ($dashPath) {
                // DASH 세그먼트 파일들의 패턴을 참조 (.m4s 확장자 사용, 5자리 패딩)
                $profileName = basename(dirname($dashPath));
                $content .= '        <SegmentTemplate media="dash/' . $profileName . '/chunk-stream0-$Number%05d$.m4s" ' .
                           'startNumber="1" timescale="1000" duration="10000" ' .
                           'initialization="dash/' . $profileName . '/init-stream0.m4s"/>' . "\n";
            }
            $content .= '      </Representation>' . "\n";
        }

        $content .= '    </AdaptationSet>' . "\n";
        
        // Audio AdaptationSet (if available)
        $content .= '    <AdaptationSet mimeType="audio/mp4" segmentAlignment="true" startWithSAP="1">' . "\n";
        $content .= '      <Representation id="audio" bandwidth="128000" codecs="mp4a.40.2">' . "\n";
        
        // Use the first profile's directory for audio segments
        $firstProfile = $profiles->first();
        if ($firstProfile) {
            $profileName = basename(dirname($firstProfile->getAttribute('dash_path')));
            $content .= '        <SegmentTemplate media="dash/' . $profileName . '/chunk-stream1-$Number%05d$.m4s" ' .
                       'startNumber="1" timescale="1000" duration="10000" ' .
                       'initialization="dash/' . $profileName . '/init-stream1.m4s"/>' . "\n";
        }
        
        $content .= '      </Representation>' . "\n";
        $content .= '    </AdaptationSet>' . "\n";
        
        $content .= '  </Period>' . "\n";
        $content .= '</MPD>';

        return $content;
    }

    /**
     * Estimate bandwidth from profile configuration.
     */
    private function estimateBandwidth($profile): int
    {
        // Get bitrate from config or estimate based on resolution
        $profileName = is_object($profile) && method_exists($profile, 'getAttribute')
            ? $profile->getAttribute('profile')
            : $profile->profile ?? 'default';

        $config = config("video.default_profiles.{$profileName}", []);

        if (isset($config['bitrate'])) {
            // Convert bitrate string (e.g., "2M", "500k") to bps
            $bitrate = $config['bitrate'];
            if (str_ends_with($bitrate, 'M')) {
                return (int)str_replace('M', '', $bitrate) * 1000000;
            } elseif (str_ends_with($bitrate, 'k')) {
                return (int)str_replace('k', '', $bitrate) * 1000;
            }
            return (int)$bitrate;
        }

        // Fallback estimation based on resolution
        $width = is_object($profile) && method_exists($profile, 'getAttribute')
            ? $profile->getAttribute('width')
            : $profile->width ?? 1280;
        $height = is_object($profile) && method_exists($profile, 'getAttribute')
            ? $profile->getAttribute('height')
            : $profile->height ?? 720;
        $pixels = $width * $height;

        if ($pixels >= 3840 * 2160) return 8000000;  // 4K: 8Mbps
        if ($pixels >= 1920 * 1080) return 3000000;  // 1080p: 3Mbps
        if ($pixels >= 1280 * 720) return 1500000;   // 720p: 1.5Mbps
        return 800000; // 480p and below: 800kbps
    }

    /**
     * Update ABR profiles cache for a video.
     */
    public function updateAbrProfiles(Video $video): void
    {
        // Check if progressive export is enabled in config
        if (!config('orbit-video.default_encoding.export_progressive', true)) {
            $video->update(['abr_profiles' => []]);
            return;
        }

        $availableProfiles = $video->profiles()
            ->where('encoded', true)
            ->whereNotNull('path')
            ->get()
            ->keyBy('profile')
            ->map(function ($profile) {
                return [
                    'profile' => $profile->getAttribute('profile'),
                    'width' => $profile->getAttribute('width'),
                    'height' => $profile->getAttribute('height'),
                    'framerate' => $profile->getAttribute('framerate'),
                    'path' => $profile->getAttribute('path'),
                ];
            })
            ->toArray();

        $fallbackProfiles = $this->generateFallbackProfiles($availableProfiles);

        $video->update(['abr_profiles' => $fallbackProfiles]);

        Log::info("ABR profiles updated for video: {$video->getAttribute('id')}", [
            'profiles' => array_keys($fallbackProfiles)
        ]);
    }

    /**
     * Generate fallback profiles for missing resolutions.
     */
    private function generateFallbackProfiles(array $availableProfiles): array
    {
        $allProfiles = config('orbit-video.default_profiles', []);
        $fallbackProfiles = [];

        // Convert to collection for easier manipulation
        $availableCollection = collect($availableProfiles);

        // Sort available profiles by quality (resolution * framerate)
        $sortedAvailable = $availableCollection->sortByDesc(function ($profile) {
            return ($profile['width'] ?? 0) * ($profile['height'] ?? 0) * ($profile['framerate'] ?? 30);
        });

        foreach ($allProfiles as $profileName => $config) {
            if (isset($availableProfiles[$profileName])) {
                // Profile exists, use its path
                $fallbackProfiles[$profileName] = $availableProfiles[$profileName]['path'] ?? '';
            } else {
                // Profile doesn't exist, find best fallback
                $fallback = $this->findBestFallback($config, $sortedAvailable);
                $fallbackProfiles[$profileName] = $fallback;
            }
        }

        return $fallbackProfiles;
    }

    /**
     * Find the best available profile to use as fallback.
     */
    private function findBestFallback(array $requestedConfig, $availableProfiles): string
    {
        $requestedWidth = $requestedConfig['width'] ?? 1920;
        $requestedHeight = $requestedConfig['height'] ?? 1080;
        $requestedFramerate = $requestedConfig['framerate'] ?? 30;

        // Find profiles that don't exceed requested quality
        $suitableProfiles = $availableProfiles->filter(function ($profile) use ($requestedWidth, $requestedHeight, $requestedFramerate) {
            $profileWidth = $profile['width'] ?? 0;
            $profileHeight = $profile['height'] ?? 0;
            $profileFramerate = $profile['framerate'] ?? 30;

            return $profileWidth <= $requestedWidth &&
                   $profileHeight <= $requestedHeight &&
                   $profileFramerate <= $requestedFramerate;
        });

        if ($suitableProfiles->isNotEmpty()) {
            // Return the highest quality suitable profile
            return $suitableProfiles->first()['path'] ?? '';
        }

        // No suitable profile found, return the lowest quality available
        return $availableProfiles->last()['path'] ?? '';
    }

    /**
     * Get video directory path for manifests.
     */
    private function getVideoDirectory(Video $video): string
    {
        $videoId = $video->getAttribute('id');
        $basePath = config('orbit-video.storage.video_path', 'videos/{videoId}');
        return str_replace('{videoId}', (string) $videoId, $basePath);
    }

}
