<template>
  <div class="video-upload-component">
    <!-- Vue-based upload interface can be integrated here if needed -->
    <div v-if="showVueInterface" class="vue-upload-interface">
      <div class="upload-area">
        <input 
          ref="fileInput"
          type="file"
          :accept="accept"
          :multiple="multiple"
          @change="handleFileSelect"
          class="hidden"
        >
        <div 
          @click="$refs.fileInput.click()"
          @dragover.prevent="handleDragOver"
          @dragleave.prevent="handleDragLeave" 
          @drop.prevent="handleDrop"
          :class="['upload-zone', { 'dragover': isDragging }]"
        >
          <div class="upload-content">
            <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <p class="upload-text">{{ placeholder }}</p>
            <p class="upload-help">{{ acceptedFormats }}</p>
          </div>
        </div>
      </div>

      <!-- File List -->
      <div v-if="files.length > 0" class="file-list">
        <div v-for="file in files" :key="file.id" class="file-item">
          <div class="file-info">
            <div class="file-name">{{ file.name }}</div>
            <div class="file-size">{{ formatFileSize(file.size) }}</div>
          </div>
          <div class="file-progress" v-if="file.uploading">
            <div class="progress-bar">
              <div class="progress-fill" :style="{ width: file.progress + '%' }"></div>
            </div>
            <div class="progress-text">{{ file.progress }}%</div>
          </div>
          <div class="file-actions">
            <button @click="removeFile(file.id)" class="remove-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, defineEmits, defineProps } from 'vue'

// Props
const props = defineProps({
  name: String,
  value: {
    type: [String, Array],
    default: () => []
  },
  accept: {
    type: String,
    default: 'video/*'
  },
  multiple: {
    type: Boolean,
    default: false
  },
  maxFileSize: {
    type: Number,
    default: 2048
  },
  maxFiles: {
    type: Number,
    default: 1
  },
  placeholder: {
    type: String,
    default: 'Drop video files here or click to browse'
  },
  allowedExtensions: {
    type: Array,
    default: () => ['mp4', 'avi', 'mov', 'mkv', 'webm']
  },
  showVueInterface: {
    type: Boolean,
    default: false
  }
})

// Emits
const emit = defineEmits(['update:value', 'file-added', 'file-removed', 'upload-progress', 'upload-complete'])

// State
const files = ref([])
const isDragging = ref(false)

// Computed
const acceptedFormats = computed(() => {
  return `Supported: ${props.allowedExtensions.join(', ')}`
})

// Methods
const handleFileSelect = (event) => {
  const selectedFiles = Array.from(event.target.files)
  addFiles(selectedFiles)
}

const handleDrop = (event) => {
  isDragging.value = false
  const droppedFiles = Array.from(event.dataTransfer.files)
  addFiles(droppedFiles)
}

const handleDragOver = () => {
  isDragging.value = true
}

const handleDragLeave = () => {
  isDragging.value = false
}

const addFiles = (newFiles) => {
  newFiles.forEach(file => {
    if (validateFile(file)) {
      const fileObj = {
        id: generateId(),
        name: file.name,
        size: file.size,
        file: file,
        uploading: false,
        progress: 0,
        uploaded: false
      }
      
      files.value.push(fileObj)
      emit('file-added', fileObj)
    }
  })
}

const removeFile = (fileId) => {
  const index = files.value.findIndex(f => f.id === fileId)
  if (index !== -1) {
    const file = files.value[index]
    files.value.splice(index, 1)
    emit('file-removed', file)
  }
}

const validateFile = (file) => {
  // Check file size
  if (file.size > props.maxFileSize * 1024 * 1024) {
    alert(`File too large. Maximum size: ${props.maxFileSize}MB`)
    return false
  }

  // Check file extension
  const extension = file.name.split('.').pop().toLowerCase()
  if (!props.allowedExtensions.includes(extension)) {
    alert(`File type not allowed. Allowed types: ${props.allowedExtensions.join(', ')}`)
    return false
  }

  // Check max files
  if (!props.multiple && files.value.length > 0) {
    files.value = [] // Clear existing files for single upload
  } else if (files.value.length >= props.maxFiles) {
    alert(`Maximum ${props.maxFiles} files allowed`)
    return false
  }

  return true
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const generateId = () => {
  return Math.random().toString(36).substr(2, 9)
}

// Expose methods for parent component
defineExpose({
  addFiles,
  removeFile,
  files
})
</script>

<style scoped>
.video-upload-component {
  @apply w-full;
}

.upload-zone {
  @apply border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer transition-colors duration-200;
}

.upload-zone:hover,
.upload-zone.dragover {
  @apply border-blue-500 bg-blue-50;
}

.upload-icon {
  @apply w-12 h-12 mx-auto mb-4 text-gray-400;
}

.upload-text {
  @apply text-lg font-medium text-gray-700 mb-2;
}

.upload-help {
  @apply text-sm text-gray-500;
}

.file-list {
  @apply mt-4 space-y-2;
}

.file-item {
  @apply p-4 border border-gray-200 rounded-lg;
}

.file-info {
  @apply flex justify-between items-center;
}

.file-name {
  @apply font-medium text-gray-900;
}

.file-size {
  @apply text-sm text-gray-500;
}

.file-progress {
  @apply mt-2;
}

.progress-bar {
  @apply w-full bg-gray-200 rounded-full h-2;
}

.progress-fill {
  @apply bg-blue-500 h-2 rounded-full transition-all duration-300;
}

.progress-text {
  @apply text-sm text-gray-600 mt-1;
}

.file-actions {
  @apply flex justify-end;
}

.remove-btn {
  @apply text-red-500 hover:text-red-700 p-1;
}
</style>