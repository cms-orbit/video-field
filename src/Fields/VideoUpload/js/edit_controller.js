import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'hiddenInput', 'uploadArea', 'dropZone', 'fileInput', 'browseBtn',
        'fileList', 'fileItems', 'existingVideos', 'errorContainer', 'errorMessage',
        'fileTemplate'
    ]

    static values = {
        name: String,
        maxFileSize: Number,
        chunkSize: Number,
        maxFiles: Number,
        autoProcess: Boolean,
        showProgress: Boolean,
        multiple: Boolean,
        allowedExtensions: Array,
        profiles: Array,
        autoThumbnail: Boolean,
        autoSprite: Boolean,
        value: Array,
        uploadUrl: String,
        completeUrl: String,
        cancelUrl: String
    }

    connect() {
        console.log('VideoUpload controller connected')
        
        // Initialize component state
        this.files = new Map()
        this.currentValue = this.valueValue || []
        
        // Set up event listeners
        this.setupEventListeners()
        
        // Display existing videos
        this.displayExistingVideos()
    }

    disconnect() {
        console.log('VideoUpload controller disconnected')
        // Clean up any ongoing uploads
        this.files.forEach(file => {
            if (file.uploadPromise) {
                file.uploadPromise.cancel?.()
            }
        })
    }

    setupEventListeners() {
        // File input change
        this.fileInputTarget.addEventListener('change', (e) => {
            this.handleFileSelect(e.target.files)
        })

        // Browse button click
        this.browseBtnTarget.addEventListener('click', () => {
            this.fileInputTarget.click()
        })

        // Drag and drop
        this.dropZoneTarget.addEventListener('dragover', (e) => {
            e.preventDefault()
            this.dropZoneTarget.classList.add('dragover')
        })

        this.dropZoneTarget.addEventListener('dragleave', (e) => {
            e.preventDefault()
            this.dropZoneTarget.classList.remove('dragover')
        })

        this.dropZoneTarget.addEventListener('drop', (e) => {
            e.preventDefault()
            this.dropZoneTarget.classList.remove('dragover')
            this.handleFileSelect(e.dataTransfer.files)
        })

        // Click to browse
        this.dropZoneTarget.addEventListener('click', (e) => {
            if (e.target === this.dropZoneTarget || e.target.closest('.upload-icon, .upload-text')) {
                this.fileInputTarget.click()
            }
        })
    }

    handleFileSelect(files) {
        const fileList = Array.from(files)
        
        // Validate files
        const validFiles = fileList.filter(file => this.validateFile(file))
        
        if (validFiles.length === 0) {
            return
        }

        // Check max files limit
        const currentCount = this.files.size
        const remainingSlots = this.maxFilesValue - currentCount
        
        if (validFiles.length > remainingSlots) {
            this.showError(`Maximum ${this.maxFilesValue} files allowed. You can add ${remainingSlots} more.`)
            return
        }

        // Add files to upload queue
        validFiles.forEach(file => this.addFile(file))
    }

    validateFile(file) {
        // Check file size
        const maxSize = this.maxFileSizeValue * 1024 * 1024 // Convert MB to bytes
        if (file.size > maxSize) {
            this.showError(`File "${file.name}" is too large. Maximum size: ${this.maxFileSizeValue}MB`)
            return false
        }

        // Check file extension
        const extension = file.name.split('.').pop().toLowerCase()
        if (!this.allowedExtensionsValue.includes(extension)) {
            this.showError(`File "${file.name}" type not allowed. Allowed types: ${this.allowedExtensionsValue.join(', ')}`)
            return false
        }

        return true
    }

    addFile(file) {
        const fileId = this.generateId()
        const fileObj = {
            id: fileId,
            name: file.name,
            size: file.size,
            file: file,
            status: 'pending',
            progress: 0,
            videoId: null,
            uploadPromise: null
        }

        this.files.set(fileId, fileObj)
        this.renderFileItem(fileObj)
        this.showFileList()

        // Start upload if auto process is enabled
        if (this.autoProcessValue) {
            this.uploadFile(fileId)
        }
    }

    renderFileItem(fileObj) {
        const template = this.fileTemplateTarget.content.cloneNode(true)
        const fileItem = template.querySelector('.video-file-item')
        
        // Set file ID
        fileItem.dataset.fileId = fileObj.id
        
        // Set file info
        fileItem.querySelector('.video-file-name').textContent = fileObj.name
        fileItem.querySelector('.video-file-size').textContent = this.formatFileSize(fileObj.size)
        
        // Set up remove button
        const removeBtn = fileItem.querySelector('.video-file-remove')
        removeBtn.addEventListener('click', () => this.removeFile(fileObj.id))
        
        // Add to file list
        this.fileItemsTarget.appendChild(fileItem)
        
        return fileItem
    }

    async uploadFile(fileId) {
        const fileObj = this.files.get(fileId)
        if (!fileObj) return

        const fileItem = this.findFileItem(fileId)
        if (!fileItem) return

        try {
            fileObj.status = 'uploading'
            this.updateFileStatus(fileItem, 'uploading')
            this.showProgress(fileItem)

            // Upload in chunks
            const uploadId = this.generateUploadId()
            const totalChunks = Math.ceil(fileObj.file.size / (this.chunkSizeValue * 1024 * 1024))
            
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                await this.uploadChunk(fileObj, uploadId, chunkIndex, totalChunks, fileItem)
            }

            // Complete upload
            const videoData = await this.completeUpload(uploadId, fileObj.name, totalChunks)
            
            fileObj.status = 'completed'
            fileObj.videoId = videoData.video.id
            this.updateFileStatus(fileItem, 'completed')
            this.hideProgress(fileItem)
            this.showVideoPreview(fileItem, videoData.video)

            // Update form value
            this.updateFormValue()

            // Show processing status if auto processing is enabled
            if (this.autoProcessValue) {
                this.showProcessingStatus(fileItem)
            }

        } catch (error) {
            console.error('Upload failed:', error)
            fileObj.status = 'failed'
            this.updateFileStatus(fileItem, 'failed')
            this.hideProgress(fileItem)
            this.showError(`Upload failed for "${fileObj.name}": ${error.message}`)
        }
    }

    async uploadChunk(fileObj, uploadId, chunkIndex, totalChunks, fileItem) {
        const chunkSize = this.chunkSizeValue * 1024 * 1024
        const start = chunkIndex * chunkSize
        const end = Math.min(start + chunkSize, fileObj.file.size)
        const chunk = fileObj.file.slice(start, end)

        const formData = new FormData()
        formData.append('chunk', chunk)
        formData.append('chunk_number', chunkIndex)
        formData.append('total_chunks', totalChunks)
        formData.append('upload_id', uploadId)
        formData.append('filename', fileObj.name)

        const response = await fetch(this.uploadUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })

        if (!response.ok) {
            throw new Error(`Chunk upload failed: ${response.statusText}`)
        }

        // Update progress
        const progress = Math.round(((chunkIndex + 1) / totalChunks) * 100)
        fileObj.progress = progress
        this.updateProgress(fileItem, progress)
    }

    async completeUpload(uploadId, filename, totalChunks) {
        const response = await fetch(this.completeUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                upload_id: uploadId,
                filename: filename,
                total_chunks: totalChunks,
                profiles: this.profilesValue,
                auto_thumbnail: this.autoThumbnailValue,
                auto_sprite: this.autoSpriteValue
            })
        })

        if (!response.ok) {
            throw new Error(`Upload completion failed: ${response.statusText}`)
        }

        return await response.json()
    }

    removeFile(fileId) {
        const fileObj = this.files.get(fileId)
        if (!fileObj) return

        // Cancel upload if in progress
        if (fileObj.uploadPromise) {
            fileObj.uploadPromise.cancel?.()
        }

        // Remove from files map
        this.files.delete(fileId)

        // Remove from DOM
        const fileItem = this.findFileItem(fileId)
        if (fileItem) {
            fileItem.remove()
        }

        // Update form value
        this.updateFormValue()

        // Hide file list if empty
        if (this.files.size === 0) {
            this.hideFileList()
        }
    }

    updateFormValue() {
        const videoIds = Array.from(this.files.values())
            .filter(file => file.videoId)
            .map(file => file.videoId)

        this.currentValue = this.multipleValue ? videoIds : (videoIds[0] || null)
        this.hiddenInputTarget.value = JSON.stringify(this.currentValue)

        // Dispatch change event
        this.hiddenInputTarget.dispatchEvent(new Event('change', { bubbles: true }))
    }

    // UI Helper Methods
    showFileList() {
        this.fileListTarget.style.display = 'block'
    }

    hideFileList() {
        this.fileListTarget.style.display = 'none'
    }

    showProgress(fileItem) {
        const progressEl = fileItem.querySelector('.video-file-progress')
        if (progressEl) {
            progressEl.style.display = 'block'
        }
    }

    hideProgress(fileItem) {
        const progressEl = fileItem.querySelector('.video-file-progress')
        if (progressEl) {
            progressEl.style.display = 'none'
        }
    }

    updateProgress(fileItem, progress) {
        const progressBar = fileItem.querySelector('.progress-bar')
        const progressText = fileItem.querySelector('.progress-text')
        
        if (progressBar) {
            progressBar.style.width = `${progress}%`
            progressBar.setAttribute('aria-valuenow', progress)
        }
        
        if (progressText) {
            progressText.textContent = `Uploading... ${progress}%`
        }
    }

    updateFileStatus(fileItem, status) {
        const statusEl = fileItem.querySelector('.video-file-status')
        const statusBadges = {
            pending: '<span class="badge badge-secondary">Pending</span>',
            uploading: '<span class="badge badge-primary">Uploading</span>',
            completed: '<span class="badge badge-success">Uploaded</span>',
            failed: '<span class="badge badge-danger">Failed</span>',
            processing: '<span class="badge badge-info">Processing</span>'
        }

        if (statusEl && statusBadges[status]) {
            statusEl.innerHTML = statusBadges[status]
        }
    }

    showVideoPreview(fileItem, videoData) {
        const previewEl = fileItem.querySelector('.video-preview')
        if (previewEl && videoData.thumbnail_url) {
            const video = previewEl.querySelector('video')
            if (video && videoData.url) {
                video.src = videoData.url
                video.poster = videoData.thumbnail_url
            }

            const durationEl = previewEl.querySelector('.video-duration')
            const resolutionEl = previewEl.querySelector('.video-resolution')
            
            if (durationEl && videoData.duration) {
                durationEl.textContent = `Duration: ${this.formatDuration(videoData.duration)}`
            }
            
            if (resolutionEl && videoData.width && videoData.height) {
                resolutionEl.textContent = `Resolution: ${videoData.width}x${videoData.height}`
            }

            previewEl.style.display = 'block'
        }
    }

    showProcessingStatus(fileItem) {
        const processingEl = fileItem.querySelector('.processing-status')
        if (processingEl) {
            processingEl.style.display = 'block'
        }
    }

    showError(message) {
        this.errorMessageTarget.textContent = message
        this.errorContainerTarget.style.display = 'block'
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            this.errorContainerTarget.style.display = 'none'
        }, 5000)
    }

    displayExistingVideos() {
        if (!this.currentValue || (Array.isArray(this.currentValue) && this.currentValue.length === 0)) {
            return
        }

        // TODO: Fetch and display existing videos
        // This would require an API endpoint to get video data by IDs
    }

    // Utility Methods
    findFileItem(fileId) {
        return this.fileItemsTarget.querySelector(`[data-file-id="${fileId}"]`)
    }

    generateId() {
        return Math.random().toString(36).substr(2, 9)
    }

    generateUploadId() {
        return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9)
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes'
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB', 'GB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    }

    formatDuration(seconds) {
        const minutes = Math.floor(seconds / 60)
        const remainingSeconds = Math.floor(seconds % 60)
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`
    }
}