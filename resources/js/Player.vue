<template>
    <div class="video-player-wrapper">
        <!-- 로딩 상태 -->
        <div v-if="loading" class="video-player-loading">
            <div class="loading-spinner"></div>
            <p>{{ __('Loading video...') }}</p>
        </div>

        <!-- 에러 상태 -->
        <div v-else-if="error" class="video-player-error">
            <div class="error-icon">⚠️</div>
            <h3>{{ __('Failed to load video') }}</h3>
            <p>{{ __('This Video is not available') }}</p>
        </div>

        <!-- 비디오 플레이어 -->
        <div v-else-if="videoData" class="video-player-container">
            <!-- 인코딩 중 -->
            <div v-if="videoData.status !== 'completed'" class="video-player-processing">
                <div class="processing-icon">⏳</div>
                <h3>{{ __('Video is being processed') }}</h3>
                <div class="progress-bar">
                    <div class="progress-bar-fill" :style="{ width: videoData.encoding_progress + '%' }"></div>
                </div>
                <p>{{ videoData.encoding_progress }}% {{ __('complete') }}</p>
            </div>

            <!-- 비디오 재생 -->
            <div 
                v-else 
                class="video-player"
                tabindex="0"
                @mouseenter="showOverlayControls = true"
                @mousemove="handleMouseMove"
                @mouseleave="hideOverlayControls"
                @keydown="handleKeyDown"
            >
                <!-- 빨리감기/되감기 인디케이터 -->
                <transition name="fade">
                    <div v-if="seekIndicator.show" class="seek-indicator" :class="seekIndicator.direction">
                        <svg v-if="seekIndicator.direction === 'forward'" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 18l8.5-6L4 6v12zm9-12v12l8.5-6L13 6z"/>
                        </svg>
                        <svg v-else viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11 18V6l-8.5 6 8.5 6zm.5-6l8.5 6V6l-8.5 6z"/>
                        </svg>
                        <span>{{ seekIndicator.seconds }}{{ __('seconds') }}</span>
                    </div>
                </transition>

                <!-- 오버레이 컨트롤 (마우스 오버 시) -->
                <transition name="fade">
                    <div v-if="showOverlayControls && !useNativeControls" class="video-overlay-controls">
                        <!-- 좌측 되감기 버튼 -->
                        <button 
                            class="overlay-control-btn left"
                            @click.stop="seekBackwardLarge"
                            :title="__('30 seconds backward')"
                        >
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.99 5V1l-5 5 5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6h-2c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/>
                                <text x="12" y="16" text-anchor="middle" font-size="8" font-weight="bold" fill="currentColor">30</text>
                            </svg>
                        </button>

                        <!-- 중앙 재생/정지 버튼 -->
                        <button 
                            class="overlay-control-btn center"
                            @click.stop="togglePlay"
                            :title="isPlaying ? __('Pause') : __('Play')"
                        >
                            <svg v-if="!isPlaying" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            <svg v-else viewBox="0 0 24 24" fill="currentColor">
                                <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                            </svg>
                        </button>

                        <!-- 우측 빨리감기 버튼 -->
                        <button 
                            class="overlay-control-btn right"
                            @click.stop="seekForwardLarge"
                            :title="__('30 seconds forward')"
                        >
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 5V1l5 5-5 5V7c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6h2c0 4.42-3.58 8-8 8s-8-3.58-8-8 3.58-8 8-8z"/>
                                <text x="12" y="16" text-anchor="middle" font-size="8" font-weight="bold" fill="currentColor">30</text>
                            </svg>
                        </button>
                    </div>
                </transition>

                <!-- 좌측 클릭/더블클릭 영역 -->
                <div 
                    class="video-seek-area left"
                    @click="togglePlay"
                    @dblclick.stop="seekBackward"
                ></div>

                <!-- 중앙 클릭 영역 -->
                <div 
                    class="video-seek-area center"
                    @click="togglePlay"
                ></div>

                <!-- 우측 클릭/더블클릭 영역 -->
                <div 
                    class="video-seek-area right"
                    @click="togglePlay"
                    @dblclick.stop="seekForward"
                ></div>

                <video
                    ref="videoElement"
                    :poster="videoData.thumbnail_url"
                    :controls="useNativeControls"
                    :autoplay="autoplay"
                    :loop="loop"
                    :muted="muted"
                    :playsinline="playsinline"
                    :preload="preload"
                    class="video-element"
                    @error="handleVideoError"
                    @timeupdate="handleTimeUpdate"
                    @play="handlePlay"
                    @pause="handlePause"
                    @ended="handleEnded"
                    @loadedmetadata="handleLoadedMetadata"
                    @volumechange="handleVolumeChange"
                >
                    {{ __('Your browser does not support video playback.') }}
                </video>
                
                <!-- 제목/설명 오버레이 -->
                <transition name="fade">
                    <div 
                        v-if="showInfoOverlay && (showTitle || showDescription)"
                        class="video-info-overlay"
                    >
                        <h3 v-if="showTitle && videoData.title" class="video-title">
                            {{ videoData.title }}
                        </h3>
                        <p v-if="showDescription && videoData.description" class="video-description">
                            {{ videoData.description }}
                        </p>
                    </div>
                </transition>

                <!-- 커스텀 컨트롤 -->
                <PlayerControls
                    v-if="!useNativeControls"
                    :video-data="videoData"
                    :player="player"
                    :is-playing="isPlaying"
                    :current-time="currentTime"
                    :duration="duration"
                    :volume="volume"
                    :video-ready="videoReady"
                    :is-fullscreen="isFullscreen"
                    :buffered-percent="bufferedPercent"
                    :available-qualities="availableQualities"
                    :selected-quality-index="selectedQualityIndex"
                    :show-quality-selector="showQualitySelector"
                    :show-download="showDownload"
                    :show-sprite-preview="showSpritePreview"
                    :sprite-preview-time="spritePreviewTime"
                    :sprite-preview-position="spritePreviewPosition"
                    :sprite-frame-at-time="spriteFrameAtTime"
                    @toggle-play="togglePlay"
                    @seek="seek"
                    @toggle-mute="toggleMute"
                    @set-volume="setVolume"
                    @select-quality="selectQuality"
                    @toggle-fullscreen="toggleFullscreen"
                    @progress-hover="handleProgressHover"
                    @progress-leave="handleProgressLeave"
                >
                    <template #actions="slotProps">
                        <slot name="actions" v-bind="slotProps"></slot>
                    </template>
                </PlayerControls>
                
                <!-- 디버그 모드: 현재 화질 표시 -->
                <div v-if="debug && currentQuality" class="quality-indicator">
                    <div class="quality-label">{{ currentQuality.qualityLabel }}</div>
                    <div class="quality-bitrate">{{ Math.round(currentQuality.bitrate / 1000) }} kbps</div>
                </div>
            </div>
        </div>

        <!-- 비디오 정보 없음 -->
        <div v-else class="video-player-empty">
            <div class="empty-icon">📹</div>
            <p>{{ __('No video available') }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import axios from 'axios';
import { __ } from '@/lib/translate';
import PlayerControls from '@orbit/video/Components/PlayerControls.vue';
import { useVideoPlayer } from '@orbit/video/composables/useVideoPlayer';

const props = defineProps({
    video: {
        type: Object,
        required: false,
        default: null,
    },
    videoId: {
        type: Number,
        required: false,
        default: null,
    },
    debug: {
        type: Boolean,
        required: false,
        default: false,
    },
    // 비디오 기본 속성
    autoplay: {
        type: Boolean,
        required: false,
        default: false,
    },
    loop: {
        type: Boolean,
        required: false,
        default: false,
    },
    muted: {
        type: Boolean,
        required: false,
        default: false,
    },
    playsinline: {
        type: Boolean,
        required: false,
        default: true,
    },
    preload: {
        type: String,
        required: false,
        default: 'metadata',
        validator: (value) => ['none', 'metadata', 'auto'].includes(value),
    },
    // 커스텀 컨트롤 사용 여부 (false면 네이티브 controls 사용)
    useNativeControls: {
        type: Boolean,
        required: false,
        default: false,
    },
    // 화질 선택 표시 여부
    showQualitySelector: {
        type: Boolean,
        required: false,
        default: true,
    },
    // 다운로드 버튼 표시 여부
    showDownload: {
        type: Boolean,
        required: false,
        default: false,
    },
    // 제목 오버레이 표시 여부
    showTitle: {
        type: Boolean,
        required: false,
        default: false,
    },
    // 설명 오버레이 표시 여부
    showDescription: {
        type: Boolean,
        required: false,
        default: false,
    },
    // 강의 모드 (빨리감기 제한)
    lectureMode: {
        type: Boolean,
        required: false,
        default: false,
    },
});

// 비디오 데이터 및 상태
const loading = ref(true);
const error = ref(null);
const videoData = ref(null);

// 플레이어 상태
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const volume = ref(1);
const videoReady = ref(false);
const isFullscreen = ref(false);

// 시청 기록 상태
const watchHistory = ref(null);
const maxSeekableTime = ref(0);
let watchHistoryInterval = null;
let lastValidTime = ref(0); // 마지막 유효한 재생 시간 (seek 감지용)

// Sprite 프리뷰
const showSpritePreview = ref(false);
const spritePreviewTime = ref(0);
const spritePreviewPosition = ref({ x: 0, y: 0 });

// 타이틀/설명 오버레이
const showInfoOverlay = ref(false);

// 오버레이 컨트롤
const showOverlayControls = ref(false);
let overlayControlsTimeout = null;

// 빨리감기/되감기 인디케이터
const seekIndicator = ref({
    show: false,
    direction: 'forward', // 'forward' or 'backward'
    seconds: 10,
});
let seekIndicatorTimeout = null;

// 비디오 플레이어 composable 사용
const {
    videoElement,
    player,
    currentQuality,
    availableQualities,
    selectedQualityIndex,
    loadPlayerScripts,
    initializePlayer,
    selectQuality,
    cleanupPlayer,
} = useVideoPlayer(props);

// Computed
const playedPercent = computed(() => {
    return duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0;
});

const bufferedPercent = computed(() => {
    if (!videoElement.value || !videoElement.value.buffered.length) return 0;
    const buffered = videoElement.value.buffered.end(videoElement.value.buffered.length - 1);
    return duration.value > 0 ? (buffered / duration.value) * 100 : 0;
});

// Sprite 정보
const hasSpritePreview = computed(() => {
    return !!(videoData.value?.sprite_url && videoData.value?.sprite_metadata);
});

const spriteFrameAtTime = computed(() => {
    if (!hasSpritePreview.value || !spritePreviewTime.value) return null;
    
    const metadata = videoData.value.sprite_metadata;
    if (!metadata?.frames) return null;
    
    // 가장 가까운 프레임 찾기
    const frames = metadata.frames;
    let closestFrame = frames[0];
    let minDiff = Math.abs(frames[0].time - spritePreviewTime.value);
    
    for (const frame of frames) {
        const diff = Math.abs(frame.time - spritePreviewTime.value);
        if (diff < minDiff) {
            minDiff = diff;
            closestFrame = frame;
        }
    }
    
    return closestFrame;
});

// 디버그 로그 헬퍼
const debugLog = (...args) => {
    if (props.debug) {
        console.log('[Video Player]', ...args);
    }
};

// 비디오 이벤트 핸들러
const handleTimeUpdate = () => {
    if (videoElement.value) {
        const newTime = videoElement.value.currentTime;
        const timeDiff = newTime - lastValidTime.value;
        
        currentTime.value = newTime;
        
        // 강의 모드일 때 빨리감기 제한
        if (props.lectureMode && maxSeekableTime.value > 0) {
            // 시간이 2초 이상 갑자기 뛰었다면 seek로 판단 (빨리감기 시도)
            if (timeDiff > 2 && newTime > maxSeekableTime.value) {
                // 허용된 시간을 초과하는 seek는 차단
                videoElement.value.currentTime = maxSeekableTime.value;
                lastValidTime.value = maxSeekableTime.value;
                debugLog('Seek blocked: max seekable time is', maxSeekableTime.value);
                return;
            }
        }
        
        // 정상 재생: lastValidTime과 maxSeekableTime 업데이트
        lastValidTime.value = newTime;
        if (newTime > maxSeekableTime.value) {
            maxSeekableTime.value = newTime;
        }
    }
};

const handlePlay = () => {
    isPlaying.value = true;
};

const handlePause = () => {
    isPlaying.value = false;
};

const handleEnded = () => {
    isPlaying.value = false;
};

const handleLoadedMetadata = () => {
    if (videoElement.value) {
        duration.value = videoElement.value.duration;
        videoReady.value = true;
        debugLog('Video metadata loaded, duration:', duration.value);
        
        // 시청 기록에서 재생 위치 복원
        if (watchHistory.value?.seconds > 0) {
            videoElement.value.currentTime = watchHistory.value.seconds;
            lastValidTime.value = watchHistory.value.seconds;
            debugLog('Restored playback position:', watchHistory.value.seconds);
        }
        
        // 시청 기록 자동 저장 시작
        startWatchHistoryTracking();
    }
};

const handleVolumeChange = () => {
    if (videoElement.value) {
        volume.value = videoElement.value.volume;
    }
};

// 인디케이터 표시 함수
const showSeekIndicator = (direction, seconds = 10) => {
    seekIndicator.value = {
        show: true,
        direction,
        seconds,
    };
    
    if (seekIndicatorTimeout) {
        clearTimeout(seekIndicatorTimeout);
    }
    
    seekIndicatorTimeout = setTimeout(() => {
        seekIndicator.value.show = false;
    }, 800);
};

// 플레이어 컨트롤 함수
const togglePlay = () => {
    if (!videoElement.value) return;
    
    if (isPlaying.value) {
        videoElement.value.pause();
    } else {
        videoElement.value.play();
    }
};

const seek = (event) => {
    if (!videoElement.value || !duration.value) return;
    
    const rect = event.currentTarget.getBoundingClientRect();
    const pos = (event.clientX - rect.left) / rect.width;
    let newTime = pos * duration.value;
    
    // 강의 모드일 때 빨리감기 제한
    if (props.lectureMode && maxSeekableTime.value > 0 && newTime > maxSeekableTime.value) {
        newTime = maxSeekableTime.value;
        debugLog('Seek limited to max seekable time:', maxSeekableTime.value);
    }
    
    videoElement.value.currentTime = newTime;
};

const seekBySeconds = (seconds) => {
    if (!videoElement.value || !duration.value) return;
    
    let newTime = Math.max(0, Math.min(duration.value, currentTime.value + seconds));
    
    // 강의 모드일 때 빨리감기 제한
    if (props.lectureMode && maxSeekableTime.value > 0 && newTime > maxSeekableTime.value) {
        newTime = maxSeekableTime.value;
        debugLog('Seek limited to max seekable time:', maxSeekableTime.value);
    }
    
    videoElement.value.currentTime = newTime;
};

const seekForward = () => {
    seekBySeconds(10);
    showSeekIndicator('forward', 10);
};

const seekBackward = () => {
    seekBySeconds(-10);
    showSeekIndicator('backward', 10);
};

const seekForwardLarge = () => {
    seekBySeconds(30);
    showSeekIndicator('forward', 30);
};

const seekBackwardLarge = () => {
    seekBySeconds(-30);
    showSeekIndicator('backward', 30);
};

const toggleMute = () => {
    if (!videoElement.value) return;
    videoElement.value.muted = !videoElement.value.muted;
    if (!videoElement.value.muted && videoElement.value.volume === 0) {
        videoElement.value.volume = 0.5;
    }
};

const setVolume = (event) => {
    if (!videoElement.value) return;
    videoElement.value.volume = parseFloat(event.target.value);
};

const toggleFullscreen = async () => {
    const container = videoElement.value?.parentElement;
    if (!container) return;
    
    try {
        if (!document.fullscreenElement) {
            await container.requestFullscreen();
            isFullscreen.value = true;
        } else {
            await document.exitFullscreen();
            isFullscreen.value = false;
        }
    } catch (err) {
        console.error('[Video Player] Fullscreen error:', err);
    }
};

// 키보드 단축키 핸들러
const handleKeyDown = (event) => {
    // input, textarea 등에서는 동작하지 않도록
    if (['INPUT', 'TEXTAREA'].includes(event.target.tagName)) {
        return;
    }
    
    switch(event.key) {
        case ' ':
        case 'k':
        case 'K':
            event.preventDefault();
            togglePlay();
            break;
        case 'ArrowLeft':
            event.preventDefault();
            seekBackward();
            break;
        case 'ArrowRight':
            event.preventDefault();
            seekForward();
            break;
        case 'ArrowUp':
            event.preventDefault();
            if (videoElement.value) {
                videoElement.value.volume = Math.min(1, volume.value + 0.1);
            }
            break;
        case 'ArrowDown':
            event.preventDefault();
            if (videoElement.value) {
                videoElement.value.volume = Math.max(0, volume.value - 0.1);
            }
            break;
        case 'm':
        case 'M':
            event.preventDefault();
            toggleMute();
            break;
        case 'f':
        case 'F':
            event.preventDefault();
            toggleFullscreen();
            break;
    }
};


// Sprite 프리뷰 관련
const handleProgressHover = (event) => {
    if (!hasSpritePreview.value || !duration.value) return;
    
    const rect = event.currentTarget.getBoundingClientRect();
    const pos = (event.clientX - rect.left) / rect.width;
    const time = pos * duration.value;
    
    spritePreviewTime.value = time;
    spritePreviewPosition.value = {
        x: event.clientX - rect.left,
        y: rect.top
    };
    showSpritePreview.value = true;
};

const handleProgressLeave = () => {
    showSpritePreview.value = false;
};

// 오버레이 컨트롤 관련
const handleMouseMove = () => {
    showOverlayControls.value = true;
    showInfoOverlay.value = true;
    
    if (overlayControlsTimeout) {
        clearTimeout(overlayControlsTimeout);
    }
    
    overlayControlsTimeout = setTimeout(() => {
        if (!isPlaying.value) return; // 일시정지 상태면 계속 표시
        showOverlayControls.value = false;
        showInfoOverlay.value = false;
    }, 3000);
};

const hideOverlayControls = () => {
    if (overlayControlsTimeout) {
        clearTimeout(overlayControlsTimeout);
    }
    showOverlayControls.value = false;
    showInfoOverlay.value = false;
};

// 비디오 ID 추출
const extractedVideoId = computed(() => {
    if (props.video?.id) {
        return props.video.id;
    }
    return props.videoId;
});

// 시청 기록 관련 함수
const startWatchHistoryTracking = () => {
    if (!videoElement.value || watchHistoryInterval) return;
    
    const interval = 5000; // 5초마다 저장
    
    watchHistoryInterval = setInterval(() => {
        if (isPlaying.value) {
            saveWatchProgress();
        }
    }, interval);
    
    debugLog('Watch history tracking started');
};

const stopWatchHistoryTracking = () => {
    if (watchHistoryInterval) {
        clearInterval(watchHistoryInterval);
        watchHistoryInterval = null;
        debugLog('Watch history tracking stopped');
    }
};

const saveWatchProgress = async () => {
    const id = extractedVideoId.value;
    if (!id || !videoElement.value) return;
    
    try {
        const response = await axios.post(`/api/orbit-video-player/${id}/progress`, {
            current_time: currentTime.value,
            duration: duration.value,
        });
        
        if (response.data.success && response.data.data) {
            const oldPlayed = watchHistory.value?.played || 0;
            watchHistory.value = response.data.data;
            
            // 서버에서 업데이트된 played 값으로 maxSeekableTime 갱신
            // 단, 클라이언트의 현재 재생 위치가 더 크면 그것을 사용
            maxSeekableTime.value = Math.max(
                watchHistory.value.played,
                currentTime.value
            );
            
            debugLog('Watch progress saved:', response.data.data);
        }
    } catch (err) {
        debugLog('Failed to save watch progress:', err);
    }
};

// 비디오 정보 로드
const loadVideoData = async () => {
    const id = extractedVideoId.value;
    
    if (!id) {
        error.value = __('No video ID provided');
        loading.value = false;
        return;
    }

    try {
        loading.value = true;
        error.value = null;

        debugLog('Loading video data for ID:', id);
        const response = await axios.get(`/api/orbit-video-player/${id}`);
        videoData.value = response.data;
        
        // 시청 기록 로드
        if (response.data.watch_history) {
            watchHistory.value = response.data.watch_history;
            maxSeekableTime.value = watchHistory.value.played || 0;
            debugLog('Watch history loaded:', watchHistory.value);
        }

        // loading을 먼저 false로 설정하여 DOM이 렌더링되도록 함
        loading.value = false;

        // 비디오가 완료 상태면 플레이어 초기화
        if (videoData.value.status === 'completed') {
            await initializePlayer(videoData.value);
        } else {
            debugLog('Video not ready, status:', videoData.value.status, 'progress:', videoData.value.encoding_progress + '%');
        }
    } catch (err) {
        console.error('[Video Player] Failed to load video:', err);
        error.value = err.response?.data?.message || __('Failed to load video data');
        loading.value = false;
    }
};

// 비디오 에러 핸들러
const handleVideoError = (event) => {
    const videoEl = event.target;
    const errorCode = videoEl.error?.code;
    const errorMessage = videoEl.error?.message || __('Unknown video error');
    
    console.error('[Video Player] Video element error:', errorCode, errorMessage);
    debugLog('Video error details:', {
        code: errorCode,
        message: errorMessage,
        currentQuality: currentQuality.value,
        videoData: videoData.value
    });
    error.value = __('Video playback error: ') + errorMessage;
};

onMounted(async () => {
    await loadPlayerScripts();
    await loadVideoData();
});

onBeforeUnmount(() => {
    // 시청 기록 최종 저장
    if (isPlaying.value || currentTime.value > 0) {
        saveWatchProgress();
    }
    
    stopWatchHistoryTracking();
    
    if (seekIndicatorTimeout) {
        clearTimeout(seekIndicatorTimeout);
    }
    if (overlayControlsTimeout) {
        clearTimeout(overlayControlsTimeout);
    }
    cleanupPlayer();
});
</script>

<style scoped>
.video-player-wrapper {
    background: #000;
    display: block !important;
    min-width: 0 !important;
    min-height: 0 !important;
    max-width: 100% !important;
    max-height: 100% !important;
    overflow: hidden !important;
    position: relative;
    box-sizing: border-box !important;
}

.video-player-wrapper > * {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.video-player-loading,
.video-player-error,
.video-player-processing,
.video-player-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: clamp(0.25rem, 2%, 1rem);
    color: #fff;
    text-align: center;
    width: 100% !important;
    height: 100% !important;
    min-width: 0 !important;
    min-height: 0 !important;
    max-width: 100% !important;
    max-height: 100% !important;
    box-sizing: border-box !important;
    flex-shrink: 1;
    overflow: hidden;
}

.loading-spinner {
    width: clamp(16px, 30%, 40px);
    height: clamp(16px, 30%, 40px);
    border: clamp(2px, 0.5vw, 3px) solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spinner 0.8s linear infinite;
    margin-bottom: clamp(0.25rem, 2%, 0.5rem);
    flex-shrink: 0;
}

@keyframes spinner {
    to { transform: rotate(360deg); }
}

.error-icon,
.processing-icon,
.empty-icon {
    font-size: clamp(1rem, 25%, 3rem);
    margin-bottom: clamp(0.125rem, 1%, 0.5rem);
    line-height: 1;
    flex-shrink: 0;
}

.video-player-error h3,
.video-player-processing h3 {
    font-size: clamp(0.625rem, 10%, 1.25rem);
    font-weight: 600;
    margin-bottom: clamp(0.125rem, 1%, 0.25rem);
    line-height: 1.2;
    word-break: break-word;
    flex-shrink: 1;
    min-width: 0;
    max-width: 100%;
}

.video-player-error p,
.video-player-processing p,
.video-player-empty p {
    color: rgba(255, 255, 255, 0.8);
    font-size: clamp(0.5rem, 8%, 0.875rem);
    line-height: 1.3;
    margin: 0;
    word-break: break-word;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    flex-shrink: 1;
    min-width: 0;
    min-height: 0;
    max-width: 100%;
}

.progress-bar {
    width: 100%;
    max-width: min(300px, 80%);
    height: clamp(3px, 3%, 8px);
    background: rgba(255, 255, 255, 0.2);
    border-radius: clamp(2px, 0.5vw, 4px);
    overflow: hidden;
    margin: clamp(0.125rem, 1%, 0.5rem) 0;
    flex-shrink: 0;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    transition: width 0.3s ease;
}

.video-player-container {
    position: relative;
    width: 100% !important;
    height: 100% !important;
    min-width: 0 !important;
    min-height: 0 !important;
    max-width: 100% !important;
    max-height: 100% !important;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden !important;
    box-sizing: border-box !important;
}

.video-player {
    width: 100% !important;
    height: 100% !important;
    min-width: 0 !important;
    min-height: 0 !important;
    max-width: 100% !important;
    max-height: 100% !important;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box !important;
    position: relative;
    outline: none;
}

.video-element {
    max-width: 100% !important;
    max-height: 100% !important;
    width: 100% !important;
    height: 100% !important;
    display: block;
    object-fit: contain;
}

/* 빨리감기/되감기 인디케이터 */
.seek-indicator {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 1.5rem 2rem;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    z-index: 20;
    pointer-events: none;
}

.seek-indicator svg {
    width: 48px;
    height: 48px;
    fill: currentColor;
}

.seek-indicator span {
    font-size: 1rem;
    font-weight: 500;
}

/* 클릭/더블클릭 영역 */
.video-seek-area {
    position: absolute;
    top: 0;
    bottom: 60px; /* 컨트롤 바 높이 제외 */
    z-index: 5;
    cursor: pointer;
}

.video-seek-area.left {
    left: 0;
    width: 30%;
}

.video-seek-area.center {
    left: 30%;
    right: 30%;
}

.video-seek-area.right {
    right: 0;
    width: 30%;
}

/* 오버레이 컨트롤 */
.video-overlay-controls {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding: 0 2rem;
    z-index: 15;
    pointer-events: none;
}

.overlay-control-btn {
    opacity:0.7;
    background: rgba(0, 0, 0, 0.6);
    border: none;
    color: #fff;
    cursor: pointer;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
    pointer-events: auto;
    backdrop-filter: blur(4px);
    width: 80px;
    height: 80px;
}

.overlay-control-btn:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: scale(1.1);
}

.overlay-control-btn:active {
    transform: scale(0.95);
}

.overlay-control-btn svg {
    width: 48px;
    height: 48px;
    fill: currentColor;
}

.overlay-control-btn.center {
    width: 100px;
    height: 100px;
}

.overlay-control-btn.center svg {
    width: 56px;
    height: 56px;
}

.quality-indicator {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    line-height: 1.3;
    pointer-events: none;
    z-index: 10;
}

.quality-label {
    font-weight: 600;
    margin-bottom: 0.125rem;
}

.quality-bitrate {
    font-size: 0.625rem;
    opacity: 0.8;
}

/* 제목/설명 오버레이 */
.video-info-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    padding: 1.5rem;
    background: linear-gradient(to bottom, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.6) 70%, transparent 100%);
    color: #fff;
    z-index: 15;
    pointer-events: none;
}

.video-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    line-height: 1.4;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
}

.video-description {
    font-size: 0.875rem;
    line-height: 1.5;
    margin: 0;
    opacity: 0.9;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Fade 트랜지션 */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* 컨트롤이 호버될 때 */
.video-player:hover :deep(.video-controls) {
    opacity: 1;
}

/* 반응형 */
@media (max-width: 768px) {
    /* 제목/설명 오버레이 반응형 */
    .video-info-overlay {
        padding: 1rem;
    }
    
    .video-title {
        font-size: 1rem;
    }
    
    .video-description {
        font-size: 0.75rem;
        -webkit-line-clamp: 2;
        line-clamp: 2;
    }
    
    /* 빨리감기/되감기 인디케이터 반응형 */
    .seek-indicator {
        padding: 1rem 1.5rem;
    }
    
    .seek-indicator svg {
        width: 36px;
        height: 36px;
    }
    
    .seek-indicator span {
        font-size: 0.875rem;
    }
    
    /* 오버레이 컨트롤 반응형 */
    .video-overlay-controls {
        padding: 0 1rem;
    }
    
    .overlay-control-btn {
        width: 60px;
        height: 60px;
        padding: 0.75rem;
    }
    
    .overlay-control-btn svg {
        width: 36px;
        height: 36px;
    }
    
    .overlay-control-btn.center {
        width: 80px;
        height: 80px;
    }
    
    .overlay-control-btn.center svg {
        width: 48px;
        height: 48px;
    }
}
</style>