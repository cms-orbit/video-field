import { ref, computed, nextTick } from 'vue';

export function useVideoPlayer(props) {
    const videoElement = ref(null);
    const player = ref(null);
    const currentQuality = ref(null);
    const scriptsLoaded = ref({
        hls: false,
        dash: false,
    });
    const availableQualities = ref([]);
    const selectedQualityIndex = ref(-1);
    
    // 디버그 로그 헬퍼
    const debugLog = (...args) => {
        if (props.debug) {
            console.log('[Video Player]', ...args);
        }
    };
    
    // 스크립트 동적 로드
    const loadScript = (src) => {
        return new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[src="${src}"]`);
            if (existing) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
            document.head.appendChild(script);
        });
    };
    
    // 필요한 스크립트들 로드
    const loadPlayerScripts = async () => {
        try {
            await Promise.all([
                loadScript('/vendor/cms-orbit/video/js/hls.js').then(() => {
                    scriptsLoaded.value.hls = true;
                    debugLog('HLS.js loaded');
                }).catch(() => {
                    debugLog('Failed to load HLS.js');
                }),
                loadScript('/vendor/cms-orbit/video/js/dashjs.js').then(() => {
                    scriptsLoaded.value.dash = true;
                    debugLog('Dash.js loaded');
                }).catch(() => {
                    debugLog('Failed to load Dash.js');
                }),
            ]);
        } catch (err) {
            console.error('[Video Player] Error loading player scripts:', err);
        }
    };
    
    // DASH 플레이어 초기화
    const tryDashPlayer = async (dashUrl) => {
        try {
            if (typeof dashjs === 'undefined') {
                debugLog('Dash.js not loaded');
                return false;
            }

            debugLog('Initializing DASH player with URL:', dashUrl);
            
            const dash = dashjs.MediaPlayer().create();
            
            dash.updateSettings({
                debug: {
                    logLevel: props.debug ? dashjs.Debug.LOG_LEVEL_INFO : dashjs.Debug.LOG_LEVEL_WARNING
                },
                streaming: {
                    buffer: {
                        fastSwitchEnabled: true,
                    }
                }
            });

            // 에러 핸들러
            dash.on('error', (e) => {
                if (e.error?.message?.includes('SourceBuffer') || 
                    e.error?.message?.includes('buffered') ||
                    e.error?.message?.includes('removed from the parent media source')) {
                    return;
                }
                console.error('[Video Player] DASH Error:', e);
            });

            // 화질 전환 이벤트
            dash.on(dashjs.MediaPlayer.events.QUALITY_CHANGE_REQUESTED, (e) => {
                debugLog('DASH quality change requested:', e);
            });

            dash.on(dashjs.MediaPlayer.events.QUALITY_CHANGE_RENDERED, (e) => {
                const videoInfo = dash.getBitrateInfoListFor('video');
                const currentQualityIndex = dash.getQualityFor('video');
                
                if (videoInfo && videoInfo[currentQualityIndex]) {
                    const quality = videoInfo[currentQualityIndex];
                    currentQuality.value = {
                        width: quality.width,
                        height: quality.height,
                        bitrate: quality.bitrate,
                        qualityLabel: `${quality.width}x${quality.height}`
                    };
                    debugLog('DASH quality changed to:', currentQuality.value);
                }
            });

            dash.on(dashjs.MediaPlayer.events.STREAM_INITIALIZED, () => {
                debugLog('DASH stream initialized');
                const videoInfo = dash.getBitrateInfoListFor('video');
                const currentQualityIndex = dash.getQualityFor('video');
                
                // 사용 가능한 화질 목록 구성
                if (videoInfo) {
                    availableQualities.value = [
                        { index: -1, label: 'Auto', width: 0, height: 0, bitrate: 0 },
                        ...videoInfo.map((quality, index) => ({
                            index,
                            label: `${quality.height}p`,
                            width: quality.width,
                            height: quality.height,
                            bitrate: quality.bitrate
                        })).sort((a, b) => b.height - a.height)
                    ];
                    debugLog('DASH available qualities:', availableQualities.value);
                }
                
                if (videoInfo && videoInfo[currentQualityIndex]) {
                    const quality = videoInfo[currentQualityIndex];
                    currentQuality.value = {
                        width: quality.width,
                        height: quality.height,
                        bitrate: quality.bitrate,
                        qualityLabel: `${quality.width}x${quality.height}`
                    };
                    debugLog('DASH initial quality:', currentQuality.value);
                }
            });

            dash.initialize(videoElement.value, dashUrl, false);
            player.value = dash;
            
            debugLog('DASH player initialized successfully');
            return true;
        } catch (err) {
            console.error('[Video Player] DASH initialization failed:', err);
            return false;
        }
    };
    
    // HLS 플레이어 초기화
    const tryHlsPlayer = async (hlsUrl) => {
        try {
            if (typeof Hls === 'undefined') {
                debugLog('Hls.js not loaded');
                if (videoElement.value.canPlayType('application/vnd.apple.mpegurl')) {
                    debugLog('Using native HLS support');
                    videoElement.value.src = hlsUrl;
                    return true;
                }
                return false;
            }

            if (!Hls.isSupported()) {
                debugLog('HLS.js not supported, trying native');
                if (videoElement.value.canPlayType('application/vnd.apple.mpegurl')) {
                    debugLog('Using native HLS support');
                    videoElement.value.src = hlsUrl;
                    return true;
                }
                return false;
            }

            debugLog('Initializing HLS player with URL:', hlsUrl);
            
            const hls = new Hls({
                debug: props.debug,
                enableWorker: true,
                lowLatencyMode: false,
                backBufferLength: 30,
                maxBufferLength: 30,
                maxBufferSize: 60 * 1000 * 1000,
                maxBufferHole: 0.1,
                startLevel: -1,
                capLevelToPlayerSize: true,
                fragLoadingMaxRetry: 6,
                manifestLoadingMaxRetry: 4,
            });

            hls.loadSource(hlsUrl);
            hls.attachMedia(videoElement.value);

            // 에러 핸들러
            hls.on(Hls.Events.ERROR, (event, data) => {
                if (data.details === 'bufferAddCodecError' ||
                    data.details === 'bufferAppendError' ||
                    data.details === 'bufferFullError' ||
                    data.details === 'bufferStalledError') {
                    return;
                }

                if (data.fatal) {
                    console.error('[Video Player] HLS Fatal Error:', data);
                }
            });

            // 화질 전환 이벤트
            hls.on(Hls.Events.LEVEL_SWITCHING, (event, data) => {
                debugLog('HLS quality switching to level:', data.level);
            });

            hls.on(Hls.Events.LEVEL_SWITCHED, (event, data) => {
                const level = hls.levels[data.level];
                if (level) {
                    currentQuality.value = {
                        width: level.width,
                        height: level.height,
                        bitrate: level.bitrate,
                        qualityLabel: `${level.width}x${level.height}`
                    };
                    debugLog('HLS quality switched to:', currentQuality.value);
                }
            });

            // 매니페스트 로드 완료
            hls.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
                debugLog('HLS manifest parsed, available levels:', data.levels.length);
                data.levels.forEach((level, index) => {
                    debugLog(`  Level ${index}: ${level.width}x${level.height} @ ${Math.round(level.bitrate / 1000)}kbps`);
                });
                
                // 사용 가능한 화질 목록 구성
                availableQualities.value = [
                    { index: -1, label: 'Auto', width: 0, height: 0, bitrate: 0 },
                    ...data.levels.map((level, index) => ({
                        index,
                        label: `${level.height}p`,
                        width: level.width,
                        height: level.height,
                        bitrate: level.bitrate
                    })).sort((a, b) => b.height - a.height)
                ];
                debugLog('HLS available qualities:', availableQualities.value);
                
                // 초기 화질 설정
                const currentLevel = hls.currentLevel !== -1 ? hls.levels[hls.currentLevel] : hls.levels[hls.firstLevel];
                if (currentLevel) {
                    currentQuality.value = {
                        width: currentLevel.width,
                        height: currentLevel.height,
                        bitrate: currentLevel.bitrate,
                        qualityLabel: `${currentLevel.width}x${currentLevel.height}`
                    };
                    debugLog('HLS initial quality:', currentQuality.value);
                }
            });

            player.value = hls;
            
            debugLog('HLS player initialized successfully');
            return true;
        } catch (err) {
            console.error('[Video Player] HLS initialization failed:', err);
            return false;
        }
    };
    
    // Progressive 플레이어 초기화
    const tryProgressivePlayer = async (progressiveUrl, videoData) => {
        try {
            if (!progressiveUrl) {
                return false;
            }

            debugLog('Initializing Progressive player with URL:', progressiveUrl);
            videoElement.value.src = progressiveUrl;
            
            // Progressive는 단일 화질
            if (videoData.profiles && videoData.profiles.length > 0) {
                const profile = videoData.profiles[0];
                currentQuality.value = {
                    width: profile.width,
                    height: profile.height,
                    bitrate: 0,
                    qualityLabel: profile.quality_label || `${profile.width}x${profile.height}`
                };
                debugLog('Progressive quality:', currentQuality.value);
            }
            
            debugLog('Progressive player initialized successfully');
            return true;
        } catch (err) {
            console.error('[Video Player] Progressive player initialization failed:', err);
            return false;
        }
    };
    
    // 플레이어 초기화
    const initializePlayer = async (videoData) => {
        await nextTick();
        
        // 엘리먼트가 실제로 존재할 때까지 최대 3초 대기
        let attempts = 0;
        while (!videoElement.value && attempts < 30) {
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }

        if (!videoElement.value) {
            console.error('[Video Player] Video element not found after waiting');
            throw new Error('Failed to initialize video player');
        }

        debugLog('Initializing player for video:', videoData.id, videoData.title);

        const streaming = videoData.streaming;

        // DASH -> HLS -> Progressive 순서로 시도
        if (streaming.dash && await tryDashPlayer(streaming.dash)) {
            debugLog('Using DASH streaming');
        } else if (streaming.hls && await tryHlsPlayer(streaming.hls)) {
            debugLog('Using HLS streaming');
        } else if (streaming.progressive && await tryProgressivePlayer(streaming.progressive, videoData)) {
            debugLog('Using Progressive streaming');
        } else {
            throw new Error('No compatible video format available');
        }
    };
    
    // 화질 선택
    const selectQuality = (quality) => {
        debugLog('Selecting quality:', quality);
        selectedQualityIndex.value = quality.index;
        
        if (player.value) {
            if (player.value.setQualityFor) {
                // DASH
                player.value.updateSettings({
                    streaming: {
                        abr: {
                            autoSwitchBitrate: {
                                video: quality.index === -1
                            }
                        }
                    }
                });
                
                if (quality.index !== -1) {
                    player.value.setQualityFor('video', quality.index);
                }
            } else if (player.value.levels) {
                // HLS
                player.value.currentLevel = quality.index;
            }
        }
    };
    
    // 플레이어 정리
    const cleanupPlayer = () => {
        debugLog('Cleaning up player');
        
        if (player.value) {
            try {
                if (typeof player.value.destroy === 'function') {
                    player.value.destroy();
                    debugLog('Player destroyed');
                } else if (typeof player.value.reset === 'function') {
                    player.value.reset();
                    debugLog('Player reset');
                }
            } catch (err) {
                console.error('[Video Player] Error cleaning up player:', err);
            }
            player.value = null;
        }
        
        // 비디오 엘리먼트도 정리
        if (videoElement.value) {
            try {
                videoElement.value.pause();
                videoElement.value.removeAttribute('src');
                videoElement.value.load();
            } catch (err) {
                // 엘리먼트가 이미 제거된 경우 무시
            }
        }
        
        currentQuality.value = null;
    };
    
    return {
        videoElement,
        player,
        currentQuality,
        scriptsLoaded,
        availableQualities,
        selectedQualityIndex,
        loadPlayerScripts,
        initializePlayer,
        selectQuality,
        cleanupPlayer,
    };
}

