import { ref, computed, onMounted, onUnmounted } from 'vue'
import { __ } from '@/lib/translate'

export function useVideoField(props) {
    // State
    const selectedVideo = ref(null)
    const previousSelectedVideo = ref(null)
    const recentVideos = ref([])
    let searchTimeout = null

    // Computed
    const currentState = computed(() => {
        return selectedVideo.value ? 'selected' : 'selection'
    })

    const hiddenInputValue = computed(() => {
        if (!selectedVideo.value) return ''
        return JSON.stringify({
            type: 'existing',
            video_id: selectedVideo.value.id,
            video: selectedVideo.value
        })
    })

    const uploadInfo = computed(() => {
        const extensions = ['MP4', 'AVI', 'MOV', 'MKV', 'WMV', 'FLV', 'WEBM']
        const maxSizeMB = props.size
        const maxSizeGB = maxSizeMB >= 1024 ? (maxSizeMB / 1024).toFixed(1) + 'GB' : maxSizeMB + 'MB'
        return __('Supported formats: :formats. Max size: :size', {
            formats: extensions.join(', '),
            size: maxSizeGB
        })
    })

    const showCancelButton = computed(() => {
        return previousSelectedVideo.value && 
               selectedVideo.value && 
               previousSelectedVideo.value.id !== selectedVideo.value.id
    })

    // API Methods
    const loadRecentVideos = async () => {
        if (!props.recentUrl) return
        
        try {
            const response = await fetch(props.recentUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.head.querySelector('meta[name="csrf_token"]').content
                }
            })
            const data = await response.json()
            
            if (data.data && Array.isArray(data.data)) {
                recentVideos.value = data.data
            } else if (data.success) {
                recentVideos.value = data.videos || []
            }
        } catch (error) {
            console.error('Failed to load recent videos:', error)
        }
    }

    const performSearch = async (query) => {
        if (searchTimeout) {
            clearTimeout(searchTimeout)
        }
        
        searchTimeout = setTimeout(async () => {
            if (!query || query.length < 2) {
                await loadRecentVideos()
                return
            }
            
            if (!props.ajaxUrl) return
            
            try {
                const response = await fetch(`${props.ajaxUrl}?q=${encodeURIComponent(query)}&limit=${props.maxResults}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-Token': document.head.querySelector('meta[name="csrf_token"]').content
                    }
                })
                const data = await response.json()
                
                if (data.data && Array.isArray(data.data)) {
                    recentVideos.value = data.data
                } else if (data.success) {
                    recentVideos.value = data.videos || []
                }
            } catch (error) {
                console.error('Failed to search videos:', error)
            }
        }, 300)
    }

    const createVideoFromAttachment = async (attachmentId) => {
        try {
            const response = await fetch('/api/orbit-videos/create-from-attachment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.head.querySelector('meta[name="csrf_token"]').content
                },
                body: JSON.stringify({
                    attachment_id: attachmentId
                })
            })
            
            const data = await response.json()
            
            if (data.success) {
                selectVideo(data.video)
                await loadRecentVideos()
                showToast(__('Video uploaded successfully'), 'success')
            } else {
                showToast(__('Failed to create video: :message', { message: data.message }), 'danger')
            }
        } catch (error) {
            console.error('Failed to create video from attachment:', error)
            showToast(__('Failed to create video from attachment'), 'danger')
        }
    }

    // Selection Methods
    const selectVideo = (video) => {
        if (selectedVideo.value && (!previousSelectedVideo.value || previousSelectedVideo.value.id !== selectedVideo.value.id)) {
            previousSelectedVideo.value = selectedVideo.value
        }
        selectedVideo.value = video
    }

    const clearSelection = () => {
        selectedVideo.value = null
        previousSelectedVideo.value = null
    }

    const cancelSelection = () => {
        if (!previousSelectedVideo.value) {
            clearSelection()
            return
        }
        selectedVideo.value = previousSelectedVideo.value
        previousSelectedVideo.value = null
    }

    // Upload Handlers
    const handleUploadSuccess = (response) => {
        if (response && response.id) {
            createVideoFromAttachment(response.id)
        }
    }

    const handleUploadError = (errorMessage) => {
        showToast(errorMessage, 'danger')
    }

    // Utility Methods
    const showToast = (message, type = 'info') => {
        const toast = document.createElement('div')
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `
        
        document.body.appendChild(toast)
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast)
            }
        }, 5000)
    }

    const loadInitialValue = () => {
        if (!props.initialValue) return
        
        try {
            let parsed = JSON.parse(props.initialValue)
            if (typeof parsed === 'string') {
                parsed = JSON.parse(parsed)
            }
            
            if (parsed && parsed.type === 'existing' && parsed.video) {
                selectedVideo.value = parsed.video
                previousSelectedVideo.value = parsed.video
            }
        } catch (e) {
            console.warn('Failed to parse initial video field value:', e)
        }
    }

    // Lifecycle
    onMounted(() => {
        loadInitialValue()
        loadRecentVideos()
    })

    onUnmounted(() => {
        if (searchTimeout) {
            clearTimeout(searchTimeout)
        }
    })

    return {
        // State
        selectedVideo,
        previousSelectedVideo,
        recentVideos,
        
        // Computed
        currentState,
        hiddenInputValue,
        uploadInfo,
        showCancelButton,
        
        // Methods
        loadRecentVideos,
        performSearch,
        selectVideo,
        clearSelection,
        cancelSelection,
        handleUploadSuccess,
        handleUploadError,
        showToast
    }
}

