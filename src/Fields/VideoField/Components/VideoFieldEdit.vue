<template>
    <div class="video-field-container">
        <!-- Selected Video Display -->
        <VideoFieldSelected
            v-if="currentState === 'selected' && selectedVideo"
            :video="selectedVideo"
            :show-cancel-button="showCancelButton"
            @cancel="cancelSelection"
            @clear="clearSelection"
        />

        <!-- Selection & Upload Interface -->
        <VideoFieldSelection
            v-else
            :videos="recentVideos"
            :placeholder="placeholder"
            :without-exists="withoutExists"
            :without-upload="withoutUpload"
            :upload-info="uploadInfo"
            :upload-url="uploadUrl"
            :storage="storage"
            :group="group"
            :path="path"
            :max-size="size"
            :error-size="errorSize"
            :error-type="errorType"
            @select="selectVideo"
            @search="performSearch"
            @upload-success="handleUploadSuccess"
            @upload-error="handleUploadError"
        />

        <!-- Hidden Input -->
        <input type="hidden" :name="inputName" :value="hiddenInputValue">
    </div>
</template>

<script setup>
import VideoFieldSelected from './VideoFieldSelected.vue'
import VideoFieldSelection from './VideoFieldSelection.vue'
import { useVideoField } from '../js/useVideoField.js'

const props = defineProps({
    inputName: { type: String, required: true },
    initialValue: { type: String, default: '' },
    withoutUpload: { type: Boolean, default: false },
    withoutExists: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Search videos...' },
    maxResults: { type: Number, default: 10 },
    ajaxUrl: { type: String, default: '' },
    recentUrl: { type: String, default: '' },
    group: { type: String, default: 'video' },
    storage: { type: String, default: 'public' },
    path: { type: String, default: '' },
    size: { type: Number, default: 0 },
    uploadUrl: { type: String, default: '/settings/systems/files' },
    sortUrl: { type: String, default: '/settings/systems/files/sort' },
    errorSize: { type: String, default: 'File ":name" is too large to upload (max :sizeMB)' },
    errorType: { type: String, default: 'The attached file must be a video' }
})

// Use composable for business logic
const {
    selectedVideo,
    previousSelectedVideo,
    recentVideos,
    currentState,
    hiddenInputValue,
    uploadInfo,
    showCancelButton,
    loadRecentVideos,
    performSearch,
    selectVideo,
    clearSelection,
    cancelSelection,
    handleUploadSuccess,
    handleUploadError
} = useVideoField(props)
</script>

<style scoped>
.video-field-container {
    position: relative;
}
</style>
