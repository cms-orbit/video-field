<template>
    <div v-if="videoReady" class="video-controls">
        <!-- 재생/일시정지 버튼 -->
        <button @click="emit('toggle-play')" class="control-button play-button">
            <svg v-if="!isPlaying" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5v14l11-7z"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="currentColor">
                <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
            </svg>
        </button>

        <!-- 시간 표시 -->
        <div class="time-display">
            <span>{{ formatTime(currentTime) }}</span>
            <span class="time-separator">/</span>
            <span>{{ formatTime(duration) }}</span>
        </div>

        <!-- 프로그레스 바 -->
        <div 
            class="progress-container" 
            @click="emit('seek', $event)"
            @mousemove="emit('progress-hover', $event)"
            @mouseleave="emit('progress-leave')"
        >
            <div class="progress-track">
                <div class="progress-buffered" :style="{ width: bufferedPercent + '%' }"></div>
                <div class="progress-played" :style="{ width: playedPercent + '%' }"></div>
            </div>
            
            <!-- Sprite 프리뷰 -->
            <div 
                v-if="showSpritePreview && spriteFrameAtTime"
                class="sprite-preview"
                :style="{
                    left: spritePreviewPosition.x + 'px',
                    transform: 'translateX(-50%)'
                }"
            >
                <div 
                    class="sprite-frame"
                    :style="{
                        backgroundImage: `url(${videoData.sprite_url})`,
                        backgroundPosition: `-${spriteFrameAtTime.position.x}px -${spriteFrameAtTime.position.y}px`,
                        width: spriteFrameAtTime.position.width + 'px',
                        height: spriteFrameAtTime.position.height + 'px'
                    }"
                ></div>
                <div class="sprite-time">{{ formatTime(spritePreviewTime) }}</div>
            </div>
        </div>

        <!-- 볼륨 컨트롤 -->
        <div class="volume-control">
            <button @click="emit('toggle-mute')" class="control-button">
                <svg v-if="volume > 0.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                </svg>
                <svg v-else-if="volume > 0" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7 9v6h4l5 5V4l-5 5H7z"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="currentColor">
                    <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>
                </svg>
            </button>
            <input
                type="range"
                min="0"
                max="1"
                step="0.01"
                :value="volume"
                @input="emit('set-volume', $event)"
                class="volume-slider"
            />
        </div>

        <!-- 화질 선택 -->
        <div v-if="showQualitySelector && availableQualities.length > 0" class="quality-selector">
            <button @click="toggleQualityMenu" class="control-button quality-button">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
                <span class="quality-label-text">{{ selectedQualityLabel }}</span>
            </button>
            <div v-if="showQualityDropdown" class="quality-dropdown">
                <button
                    v-for="quality in availableQualities"
                    :key="quality.index"
                    @click="selectQuality(quality)"
                    class="quality-option"
                    :class="{ active: quality.index === selectedQualityIndex }"
                >
                    {{ quality.label }}
                    <span v-if="quality.index === -1" class="auto-badge">{{ __('Auto') }}</span>
                </button>
            </div>
        </div>

        <!-- 다운로드 버튼 -->
        <div v-if="showDownload && progressiveProfiles.length > 0" class="download-control">
            <button @click="toggleDownloadMenu" class="control-button download-button">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"/>
                </svg>
            </button>
            <div v-if="showDownloadDropdown" class="download-dropdown">
                <a
                    v-for="profile in progressiveProfiles"
                    :key="profile.profile || profile.id"
                    :href="profile.url"
                    :download="getDownloadFilename(profile)"
                    class="download-option"
                    @click="closeDownloadMenu"
                >
                    <span class="download-quality">{{ profile.quality_label || profile.resolution }}</span>
                    <span v-if="profile.file_size" class="download-size">{{ formatFileSize(profile.file_size) }}</span>
                </a>
            </div>
        </div>

        <!-- 전체화면 버튼 -->
        <button @click="emit('toggle-fullscreen')" class="control-button fullscreen-button">
            <svg v-if="!isFullscreen" viewBox="0 0 24 24" fill="currentColor">
                <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="currentColor">
                <path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/>
            </svg>
        </button>

        <!-- 커스텀 액션 슬롯 -->
        <slot name="actions" :video-data="videoData" :player="player" :is-playing="isPlaying"></slot>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { __ } from '@/lib/translate';

const props = defineProps({
    videoData: {
        type: Object,
        required: true,
    },
    player: {
        type: Object,
        default: null,
    },
    isPlaying: {
        type: Boolean,
        required: true,
    },
    currentTime: {
        type: Number,
        required: true,
    },
    duration: {
        type: Number,
        required: true,
    },
    volume: {
        type: Number,
        required: true,
    },
    videoReady: {
        type: Boolean,
        required: true,
    },
    isFullscreen: {
        type: Boolean,
        required: true,
    },
    bufferedPercent: {
        type: Number,
        required: true,
    },
    availableQualities: {
        type: Array,
        required: true,
    },
    selectedQualityIndex: {
        type: Number,
        required: true,
    },
    showQualitySelector: {
        type: Boolean,
        default: true,
    },
    showDownload: {
        type: Boolean,
        default: false,
    },
    showSpritePreview: {
        type: Boolean,
        required: true,
    },
    spritePreviewTime: {
        type: Number,
        required: true,
    },
    spritePreviewPosition: {
        type: Object,
        required: true,
    },
    spriteFrameAtTime: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits([
    'toggle-play',
    'seek',
    'toggle-mute',
    'set-volume',
    'select-quality',
    'toggle-fullscreen',
    'progress-hover',
    'progress-leave',
]);

// 로컬 상태
const showQualityDropdown = ref(false);
const showDownloadDropdown = ref(false);

// Computed
const playedPercent = computed(() => {
    return props.duration > 0 ? (props.currentTime / props.duration) * 100 : 0;
});

const progressiveProfiles = computed(() => {
    if (!props.videoData || !props.videoData.profiles) return [];
    return props.videoData.profiles
        .filter(p => {
            const hasFormat = p.format ? p.format === 'progressive' : true;
            const isCompleted = p.status ? p.status === 'completed' : (p.encoded === true || !!p.url);
            return hasFormat && isCompleted && p.url;
        })
        .sort((a, b) => b.height - a.height);
});

const selectedQualityLabel = computed(() => {
    if (props.selectedQualityIndex === -1) {
        return __('Auto');
    }
    const quality = props.availableQualities.find(q => q.index === props.selectedQualityIndex);
    return quality ? quality.label : __('Auto');
});

// 유틸리티 함수
const formatTime = (seconds) => {
    if (!seconds || isNaN(seconds)) return '0:00';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    
    if (h > 0) {
        return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const formatFileSize = (bytes) => {
    if (!bytes) return '-';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
};

const getDownloadFilename = (profile) => {
    const title = props.videoData?.title || props.videoData?.filename || 'video';
    const quality = profile.quality_label || profile.resolution || `${profile.width}x${profile.height}`;
    const cleanTitle = title.replace(/\.[^/.]+$/, '');
    return `${cleanTitle}_${quality}.mp4`;
};

// 메뉴 토글
const toggleQualityMenu = () => {
    showQualityDropdown.value = !showQualityDropdown.value;
    if (showQualityDropdown.value) {
        showDownloadDropdown.value = false;
    }
};

const selectQuality = (quality) => {
    emit('select-quality', quality);
    showQualityDropdown.value = false;
};

const toggleDownloadMenu = () => {
    showDownloadDropdown.value = !showDownloadDropdown.value;
    if (showDownloadDropdown.value) {
        showQualityDropdown.value = false;
    }
};

const closeDownloadMenu = () => {
    showDownloadDropdown.value = false;
};
</script>

<style scoped>
/* 커스텀 컨트롤 */
.video-controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 10;
}

.video-controls:hover,
.video-controls:focus-within {
    opacity: 1;
}

.control-button {
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.2s ease;
    border-radius: 4px;
}

.control-button:hover {
    background: rgba(255, 255, 255, 0.1);
}

.control-button svg {
    width: 20px;
    height: 20px;
    fill: currentColor;
}

.play-button svg {
    width: 24px;
    height: 24px;
}

.time-display {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #fff;
    font-size: 0.875rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.time-separator {
    opacity: 0.5;
}

.progress-container {
    flex: 1;
    cursor: pointer;
    padding: 0.5rem 0;
    position: relative;
}

.progress-track {
    position: relative;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    overflow: hidden;
}

/* Sprite 프리뷰 */
.sprite-preview {
    position: absolute;
    bottom: calc(100% + 0.5rem);
    pointer-events: none;
    z-index: 20;
}

.sprite-frame {
    background-size: auto;
    background-repeat: no-repeat;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
}

.sprite-time {
    text-align: center;
    font-size: 0.75rem;
    color: #fff;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    white-space: nowrap;
}

.progress-buffered {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: rgba(255, 255, 255, 0.5);
    transition: width 0.2s ease;
}

.progress-played {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: #3b82f6;
    transition: width 0.1s linear;
}

.volume-control {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.volume-slider {
    width: 80px;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
}

.volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
}

.volume-slider::-moz-range-thumb {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    border: none;
}

/* 화질 선택 */
.quality-selector {
    position: relative;
}

.quality-button {
    min-width: 80px;
    justify-content: center;
}

.quality-label-text {
    font-size: 0.875rem;
    font-weight: 500;
}

.quality-dropdown {
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 0.5rem;
    background: rgba(0, 0, 0, 0.9);
    border-radius: 4px;
    overflow: hidden;
    min-width: 120px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.quality-option {
    width: 100%;
    padding: 0.75rem 1rem;
    background: transparent;
    border: none;
    color: #fff;
    text-align: left;
    cursor: pointer;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background 0.2s ease;
}

.quality-option:hover {
    background: rgba(255, 255, 255, 0.1);
}

.quality-option.active {
    background: rgba(59, 130, 246, 0.5);
}

.auto-badge {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    background: rgba(59, 130, 246, 0.3);
    border-radius: 3px;
}

/* 다운로드 버튼 */
.download-control {
    position: relative;
}

.download-dropdown {
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 0.5rem;
    background: rgba(0, 0, 0, 0.9);
    border-radius: 4px;
    overflow: hidden;
    min-width: 200px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.download-option {
    width: 100%;
    padding: 0.75rem 1rem;
    background: transparent;
    color: #fff;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.875rem;
    transition: background 0.2s ease;
}

.download-option:hover {
    background: rgba(255, 255, 255, 0.1);
}

.download-quality {
    font-weight: 500;
}

.download-size {
    font-size: 0.75rem;
    opacity: 0.7;
}

/* 반응형 */
@media (max-width: 768px) {
    .video-controls {
        padding: 0.5rem;
        gap: 0.25rem;
    }
    
    .control-button {
        padding: 0.375rem;
    }
    
    .control-button svg {
        width: 18px;
        height: 18px;
    }
    
    .play-button svg {
        width: 20px;
        height: 20px;
    }
    
    .time-display {
        font-size: 0.75rem;
    }
    
    .volume-control {
        display: none;
    }
    
    .quality-label-text {
        display: none;
    }
}
</style>

