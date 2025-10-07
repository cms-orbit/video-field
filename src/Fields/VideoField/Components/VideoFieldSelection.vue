<template>
    <div>
        <!-- Search Section -->
        <div v-if="!withoutExists" class="mb-4">
            <label class="form-label">{{ __('Select Existing Video') }}</label>

            <!-- Search Input with Inline Dropdown -->
            <div class="position-relative">
                <input
                    type="text"
                    class="form-control"
                    :placeholder="placeholder"
                    v-model="searchQuery"
                    @input="handleSearchInput"
                    @focus="showResults = true"
                    @blur="handleBlur"
                />
                
                <!-- Inline Search Results Dropdown -->
                <div
                    v-if="showResults && videos.length > 0"
                    class="dropdown-menu show w-100 mt-1 shadow-sm"
                    style="max-height: 300px; overflow-y: auto; z-index: 1000;"
                >
                    <button
                        v-for="video in videos"
                        :key="video.id"
                        type="button"
                        class="dropdown-item d-flex align-items-center gap-2 py-2"
                        @mousedown.prevent="handleVideoSelect(video)"
                    >
                        <span class="badge" :class="`bg-${getStatusColor(video.status)}`" style="width: 8px; height: 8px; padding: 0;"></span>
                        <span class="text-truncate flex-grow-1">{{ video.title || video.filename }}</span>
                        <span class="small text-muted">{{ formatDuration(video.duration) }}</span>
                    </button>
                </div>
                
                <!-- No Results Message -->
                <div
                    v-if="showResults && videos.length === 0 && searchQuery.length >= 2"
                    class="dropdown-menu show w-100 mt-1 shadow-sm"
                    style="z-index: 1000;"
                >
                    <div class="dropdown-item-text text-muted text-center py-3">
                        {{ __('No videos found') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Section -->
        <VideoUpload
            v-if="!withoutUpload"
            :upload-url="uploadUrl"
            :storage="storage"
            :group="group"
            :path="path"
            :max-size="maxSize"
            :upload-info="uploadInfo"
            :error-size="errorSize"
            :error-type="errorType"
            @success="$emit('upload-success', $event)"
            @error="$emit('upload-error', $event)"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { __ } from '@/lib/translate'
import VideoUpload from './VideoUpload.vue'

const props = defineProps({
    videos: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Search videos...' },
    withoutExists: { type: Boolean, default: false },
    withoutUpload: { type: Boolean, default: false },
    uploadInfo: { type: String, default: '' },
    uploadUrl: { type: String, default: '' },
    storage: { type: String, default: 'public' },
    group: { type: String, default: 'video' },
    path: { type: String, default: '' },
    maxSize: { type: Number, default: 0 },
    errorSize: { type: String, default: '' },
    errorType: { type: String, default: '' }
})

const emit = defineEmits(['select', 'search', 'upload-success', 'upload-error'])

const searchQuery = ref('')
const showResults = ref(false)

const handleSearchInput = () => {
    showResults.value = true
    emit('search', searchQuery.value)
}

const handleBlur = () => {
    setTimeout(() => {
        showResults.value = false
    }, 200)
}

const handleVideoSelect = (video) => {
    emit('select', video)
    searchQuery.value = ''
    showResults.value = false
}

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

const getStatusColor = (status) => {
    switch (status) {
        case 'completed': return 'success'
        case 'processing': return 'warning'
        case 'failed': return 'danger'
        default: return 'secondary'
    }
}
</script>

