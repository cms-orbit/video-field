<template>
    <div class="mb-4">
        <div class="p-2 border rounded">
            <div class="">
                <div class="flex flex-col gap-1 relative">
                    <!-- 썸네일 + 상태 뱃지 -->
                    <div class="relative w-full flex-shrink-0">
                        <span
                            class="absolute z-10"
                            style="left: 0.5rem; top: 0.5rem;"
                        >
                            <span
                                class="badge text-xs px-2 py-1"
                                :class="`bg-${getStatusColor(video.status)}`"
                                style="font-size: 0.75rem;"
                            >
                                {{ video.status || 'unknown' }}
                            </span>
                        </span>
                        <div
                            class="h-24 xl:h-28 w-full bg-cover bg-center rounded bg-gray-900 d-flex align-items-center justify-content-center text-muted"
                            :style="{ backgroundImage: video.thumbnail_url ? `url(${video.thumbnail_url})` : '' }"
                        >
                            <div v-if="!video.thumbnail_url" class="text-center w-full">
                                <i class="bs bs-play-circle h4 mb-1"></i>
                                <div class="small">{{ __('Uploaded Video') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col flex-1 justify-center relative">
                        <!-- 제목 -->
                        <div class="flex items-center">
                            <h6 class="flex-1 card-title text-base font-semibold truncate">
                                {{ video.title || video.filename }}
                            </h6>
                            <div>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="$emit('clear')"
                                    :title="__('Remove video')"
                                >
                                    <LinkSlashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <!-- 시간/용량 -->
                        <p class="card-text text-xs text-muted small mb-0">
                            {{ formatDuration(video.duration) }} • {{ formatFileSize(video.file_size) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { __ } from '@/lib/translate'
import {LinkSlashIcon} from "@heroicons/vue/16/solid";

const props = defineProps({
    video: {
        type: Object,
        required: true
    }
})

defineEmits(['clear'])

const formatDuration = (seconds) => {
    if (!seconds) return '00:00'
    const hours = Math.floor(seconds / 3600)
    const minutes = Math.floor((seconds % 3600) / 60)
    const secs = Math.floor(seconds % 60)

    if (hours > 0) {
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    }
    return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const formatFileSize = (bytes) => {
    if (!bytes) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const getStatusColor = (status) => {
    switch (status) {
        case 'completed': return 'success'
        case 'processing': return 'warning'
        case 'failed': return 'danger'
        default: return 'secondary'
    }
}
</script>

