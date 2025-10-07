<template>
    <div class="mb-4">
        <label class="form-label">{{ __('Upload New Video') }}</label>
        <div
            ref="dropzone"
            class="upload-dropzone"
            :class="{ 'is-dragging': isDragging, 'is-uploading': isUploading }"
            @click="triggerFileInput"
            @drop.prevent="handleDrop"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
        >
            <!-- Upload UI -->
            <div v-if="!isUploading && !uploadedFile" class="upload-content">
                <i class="bs bs-cloud-arrow-up h3 text-muted mb-2"></i>
                <p class="text-muted mb-2">{{ __('Click to upload or drag and drop') }}</p>
                <small class="text-muted">{{ uploadInfo }}</small>
            </div>

            <!-- Uploading Progress -->
            <div v-else-if="isUploading" class="upload-progress">
                <div class="spinner-border text-primary mb-2" role="status">
                    <span class="visually-hidden">{{ __('Uploading...') }}</span>
                </div>
                <p class="text-muted mb-2">{{ __('Uploading...') }}</p>
                <div v-if="uploadProgress > 0" class="progress" style="height: 4px; width: 200px;">
                    <div
                        class="progress-bar"
                        role="progressbar"
                        :style="{ width: uploadProgress + '%' }"
                        :aria-valuenow="uploadProgress"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>
            </div>

            <!-- Uploaded File Preview -->
            <div v-else-if="uploadedFile" class="uploaded-file">
                <div class="d-flex align-items-center gap-2">
                    <i class="bs bs-file-earmark-play text-success h4 mb-0"></i>
                    <div class="flex-grow-1">
                        <p class="mb-0 small">{{ uploadedFile.name }}</p>
                        <small class="text-muted">{{ formatFileSize(uploadedFile.size) }}</small>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        @click.stop="removeFile"
                    >
                        ×
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden File Input -->
        <input
            ref="fileInput"
            type="file"
            accept="video/*"
            style="display: none;"
            @change="handleFileSelect"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { __ } from '@/lib/translate'

const props = defineProps({
    uploadUrl: { type: String, required: true },
    storage: { type: String, default: 'public' },
    group: { type: String, default: 'video' },
    path: { type: String, default: '' },
    maxSize: { type: Number, default: 0 }, // in MB
    uploadInfo: { type: String, default: '' },
    errorSize: { type: String, default: 'File ":name" is too large to upload (max :sizeMB)' },
    errorType: { type: String, default: 'The attached file must be a video' }
})

const emit = defineEmits(['success', 'error'])

// State
const fileInput = ref(null)
const dropzone = ref(null)
const isDragging = ref(false)
const isUploading = ref(false)
const uploadProgress = ref(0)
const uploadedFile = ref(null)

// Methods
const triggerFileInput = () => {
    fileInput.value?.click()
}

const handleFileSelect = (event) => {
    const file = event.target.files?.[0]
    if (file) {
        validateAndUpload(file)
    }
}

const handleDrop = (event) => {
    isDragging.value = false
    const file = event.dataTransfer.files?.[0]
    if (file) {
        validateAndUpload(file)
    }
}

const validateAndUpload = (file) => {
    // Check if it's a video file
    if (!file.type.startsWith('video/')) {
        emit('error', props.errorType)
        return
    }

    // Check file size
    const sizeMB = file.size / 1024 / 1024
    if (props.maxSize > 0 && sizeMB > props.maxSize) {
        const errorMsg = props.errorSize
            .replace(':name', file.name)
            .replace(':sizeMB', props.maxSize.toString())
        emit('error', errorMsg)
        return
    }

    uploadFile(file)
}

const uploadFile = async (file) => {
    isUploading.value = true
    uploadProgress.value = 0
    uploadedFile.value = file

    const formData = new FormData()
    formData.append('files', file)
    formData.append('storage', props.storage)
    formData.append('group', props.group)
    formData.append('path', props.path)

    // Get CSRF token
    const token = document.head.querySelector('meta[name="csrf_token"]')?.content

    try {
        const xhr = new XMLHttpRequest()

        // Upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100)
            }
        })

        // Upload complete
        xhr.addEventListener('load', () => {
            isUploading.value = false
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText)
                emit('success', response)
            } else {
                uploadedFile.value = null
                emit('error', __('Upload failed'))
            }
        })

        // Upload error
        xhr.addEventListener('error', () => {
            isUploading.value = false
            uploadedFile.value = null
            emit('error', __('Upload failed'))
        })

        xhr.open('POST', props.uploadUrl)
        if (token) {
            xhr.setRequestHeader('X-CSRF-Token', token)
        }
        xhr.send(formData)
    } catch (error) {
        isUploading.value = false
        uploadedFile.value = null
        emit('error', __('Upload failed'))
    }
}

const removeFile = () => {
    uploadedFile.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const formatFileSize = (bytes) => {
    if (!bytes) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

defineExpose({
    removeFile
})
</script>

<style scoped>
.upload-dropzone {
    min-height: 150px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f8f9fa;
}

.upload-dropzone:hover {
    border-color: #0d6efd;
    background-color: #e7f1ff;
}

.upload-dropzone.is-dragging {
    border-color: #0d6efd;
    background-color: #cfe2ff;
    transform: scale(1.02);
}

.upload-dropzone.is-uploading {
    cursor: not-allowed;
    opacity: 0.8;
}

.upload-content,
.upload-progress,
.uploaded-file {
    text-align: center;
    padding: 20px;
    width: 100%;
}

.uploaded-file {
    max-width: 400px;
    margin: 0 auto;
}

.progress {
    margin: 0 auto;
}
</style>

