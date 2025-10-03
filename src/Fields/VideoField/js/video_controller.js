import { translate } from '@/lib/translate';

export default class extends  window.Controller {
    /**
     * Values for video field
     */
    static values = {
        withoutUpload: Boolean,
        withoutExists: Boolean,
        placeholder: String,
        maxResults: Number,
        ajaxUrl: String,
        recentUrl: String,
        group: String,
        name: String,
        storage: String,
        path: String,
        count: Number,
        size: Number,
        uploadUrl: String,
        sortUrl: String,
        errorSize: String,
        errorType: String
    };

    /**
     * Targets for video field
     */
    static targets = [
        'search',
        'videoSelect',
        'selectedVideoInfo',
        'uploadArea',
        'searchSection',
        'uploadSection'
    ];

    connect() {
        console.log('🎬 VideoField controller connected');
        
        // Initialize state
        this.selectedVideo = null;
        this.recentVideos = [];
        this.currentState = 'selection'; // 'selection', 'selected', 'uploading'
        
        // Initialize UI
        this.initializeUI();
        
        // Load recent videos on connect
        this.loadRecentVideos();
    }

    /**
     * Initialize UI based on current state
     */
    initializeUI() {
        this.renderUI();
        
        // Initialize upload if not disabled
        if (!this.withoutUploadValue) {
            this.initDropZone();
            this.initUploadAreaClick();
        }
    }

    /**
     * Render UI based on current state
     */
    renderUI() {
        const container = this.element.querySelector('.video-field-container');
        if (!container) return;

        if (this.currentState === 'selected' && this.selectedVideo) {
            // Show selected video info, hide search and upload
            this.renderSelectedVideo();
            this.hideSearchSection();
            this.hideUploadSection();
        } else {
            // Show search and upload options
            this.showSearchSection();
            if (!this.withoutUploadValue) {
                this.showUploadSection();
            }
            this.hideSelectedVideo();
        }
    }

    /**
     * Show search section
     */
    showSearchSection() {
        if (this.hasSearchSectionTarget) {
            this.searchSectionTarget.style.display = 'block';
        }
    }

    /**
     * Hide search section
     */
    hideSearchSection() {
        if (this.hasSearchSectionTarget) {
            this.searchSectionTarget.style.display = 'none';
        }
    }

    /**
     * Show upload section
     */
    showUploadSection() {
        if (this.hasUploadSectionTarget) {
            this.uploadSectionTarget.style.display = 'block';
        }
    }

    /**
     * Hide upload section
     */
    hideUploadSection() {
        if (this.hasUploadSectionTarget) {
            this.uploadSectionTarget.style.display = 'none';
        }
    }

    /**
     * Hide selected video
     */
    hideSelectedVideo() {
        if (this.hasSelectedVideoInfoTarget) {
            this.selectedVideoInfoTarget.style.display = 'none';
        }
    }

    /**
     * Load recent videos
     */
    async loadRecentVideos() {
        if (!this.recentUrlValue) {
            console.log('No recent URL provided');
            return;
        }

        try {
            console.log('Loading recent videos from:', this.recentUrlValue);
            const response = await fetch(this.recentUrlValue, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.head.querySelector('meta[name="csrf_token"]').content
                }
            });
            const data = await response.json();

            console.log('Recent videos response:', data);

            if (data.data && Array.isArray(data.data)) {
                this.recentVideos = data.data || [];
            } else if (data.success) {
                this.recentVideos = data.videos || [];
            } else {
                console.error('Failed to load videos:', data.message);
            }
            
            this.renderVideoSelect();
        } catch (error) {
            console.error('Failed to load recent videos:', error);
        }
    }

    /**
     * Search videos with debounce
     */
    searchVideos() {
        // Clear existing timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        // Set new timeout
        this.searchTimeout = setTimeout(() => {
            this.performSearch();
        }, 300);
    }

    /**
     * Perform actual search
     */
    async performSearch() {
        const query = this.searchTarget?.value || '';
        if (query.length < 2) {
            this.loadRecentVideos();
            return;
        }

        if (!this.ajaxUrlValue) {
            console.log('No AJAX URL provided');
            return;
        }

        try {
            console.log('Searching videos with query:', query);
            const response = await fetch(`${this.ajaxUrlValue}?q=${encodeURIComponent(query)}&limit=${this.maxResultsValue}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.head.querySelector('meta[name="csrf_token"]').content
                }
            });
            const data = await response.json();

            console.log('Search videos response:', data);

            if (data.data && Array.isArray(data.data)) {
                this.recentVideos = data.data || [];
                this.renderVideoSelect();
            } else if (data.success) {
                this.recentVideos = data.videos || [];
                this.renderVideoSelect();
            } else {
                console.error('Failed to search videos:', data.message);
            }
        } catch (error) {
            console.error('Failed to search videos:', error);
        }
    }

    /**
     * Render video select options
     */
    renderVideoSelect() {
        if (!this.hasVideoSelectTarget) {
            console.log('No video select target found');
            return;
        }

        const select = this.videoSelectTarget;
        const currentValue = select.value;

        console.log('Rendering video select with', this.recentVideos.length, 'videos');

        // Clear existing options except first
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }

        // Add video options
        this.recentVideos.forEach(video => {
            const option = document.createElement('option');
            option.value = video.id;
            option.textContent = `${video.title || video.filename} (${this.formatDuration(video.duration)})`;
            select.appendChild(option);
        });

        // Restore selection
        select.value = currentValue;

        console.log('Video select rendered with', select.children.length, 'options');
    }

    /**
     * Handle video selection
     */
    onVideoSelect(event) {
        const videoId = event.target.value;

        if (videoId) {
            const video = this.recentVideos.find(v => v.id == videoId);
            if (video) {
                this.selectVideo(video);
            }
        } else {
            this.clearSelection();
        }
    }

    /**
     * Select a video
     */
    selectVideo(video) {
        this.selectedVideo = video;
        this.currentState = 'selected';
        this.renderUI();
        this.updateHiddenInput();
    }

    /**
     * Clear video selection
     */
    clearSelection() {
        this.selectedVideo = null;
        this.currentState = 'selection';
        
        // Remove any uploaded files from Dropzone
        if (this.dropZone && this.dropZone.files.length > 0) {
            this.dropZone.removeAllFiles(true);
        }
        
        this.renderUI();
        this.updateHiddenInput();
    }

    /**
     * Render selected video info
     */
    renderSelectedVideo() {
        if (!this.hasSelectedVideoInfoTarget || !this.selectedVideo) return;

        const container = this.selectedVideoInfoTarget;

        // Check if video has thumbnail
        const hasThumbnail = this.selectedVideo.thumbnail_url && this.selectedVideo.thumbnail_url !== '';
        
        container.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            ${hasThumbnail ? 
                                `<img
                                    src="${this.selectedVideo.thumbnail_url}"
                                    alt="${this.selectedVideo.title || this.selectedVideo.filename}"
                                    class="rounded"
                                    style="width: 80px; height: 60px; object-fit: cover;"
                                />` :
                                `<div 
                                    class="rounded border border-dashed d-flex align-items-center justify-content-center text-muted"
                                    style="width: 80px; height: 60px; background-color: #f8f9fa;"
                                >
                                    <div class="text-center">
                                        <i class="bs bs-play-circle h4 mb-1"></i>
                                        <div class="small">${translate('Uploaded Video')}</div>
                                    </div>
                                </div>`
                            }
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-1">${this.selectedVideo.title || this.selectedVideo.filename}</h6>
                            <p class="card-text text-muted small mb-1">
                                ${this.formatDuration(this.selectedVideo.duration)} •
                                ${this.formatFileSize(this.selectedVideo.file_size)}
                            </p>
                            <span class="badge bg-${this.getStatusColor(this.selectedVideo.status)}">
                                ${this.selectedVideo.status || 'unknown'}
                            </span>
                        </div>
                        <div class="flex-shrink-0">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-action="click->video#clearSelection"
                                title="${translate('Remove video')}"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.style.display = 'block';
    }

    /**
     * Update hidden input value
     */
    updateHiddenInput() {
        const hiddenInput = this.element.querySelector(`input[name="${this.nameValue}"]`);
        if (hiddenInput) {
            if (this.selectedVideo) {
                hiddenInput.value = JSON.stringify({
                    type: 'existing',
                    video_id: this.selectedVideo.id,
                    video: this.selectedVideo
                });
            } else {
                hiddenInput.value = '';
            }
        }
    }

    /**
     * Initialize upload area click event
     */
    initUploadAreaClick() {
        if (!this.hasUploadSectionTarget) return;
        
        const uploadArea = this.uploadSectionTarget.querySelector('.upload-area');
        if (uploadArea) {
            uploadArea.addEventListener('click', (event) => {
                event.preventDefault();
                this.triggerFileInput(event);
            });
        }
    }

    /**
     * Initialize Dropzone for upload
     */
    initDropZone() {
        if (!this.hasUploadSectionTarget) return;

        const self = this;
        const uploadArea = this.uploadSectionTarget;

        // Create dropzone element if it doesn't exist
        if (!uploadArea.querySelector('.dropzone-wrapper')) {
            uploadArea.innerHTML = `
                <div class="dropzone-wrapper">
                    <div class="fallback">
                        <input type="file" accept="video/*" />
                    </div>
                    <div class="visual-dropzone sortable-dropzone dropzone-previews">
                        <div class="dz-message dz-preview dz-processing dz-image-preview">
                            <div class="bg-light d-flex justify-content-center align-items-center border r-2x"
                                 style="min-height: 112px;">
                                <div class="px-2 py-4">
                                    <i class="bs bs-cloud-arrow-up h3"></i>
                                    <small class="text-muted d-block mt-1">Upload video file</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        const dropzoneElement = uploadArea.querySelector('.dropzone-wrapper');
        const visualDropzone = dropzoneElement.querySelector('.visual-dropzone');

        this.dropZone = new Dropzone(dropzoneElement, {
            url: this.uploadUrlValue,
            method: 'post',
            uploadMultiple: false,
            maxFilesize: this.sizeValue,
            maxFiles: this.countValue,
            timeout: 0,
            acceptedFiles: 'video/*',
            paramName: 'files',
            previewsContainer: visualDropzone,
            addRemoveLinks: false,
            dictFileTooBig: this.errorSizeValue,
            autoDiscover: false,

            init: function () {
                this.on('addedfile', (file) => {
                    console.log('Video file added:', file.name);

                    // Check file size
                    let sizeMB = file.size / 1000 / 1000;
                    if (self.sizeValue > 0 && sizeMB > self.sizeValue) {
                        self.toast(self.errorSizeValue.replace(':name', file.name));
                        this.removeFile(file);
                        return;
                    }

                    // Add remove button
                    const removeButton = Dropzone.createElement(`
                        <a href="javascript:;" class="btn-remove" style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; text-decoration: none;">×</a>
                    `);

                    removeButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        this.removeFile(file);
                    });

                    file.previewElement.style.position = 'relative';
                    file.previewElement.appendChild(removeButton);
                });

                this.on('sending', (file, xhr, formData) => {
                    let token = document.head.querySelector('meta[name="csrf_token"]').content;
                    formData.append('_token', token);
                    formData.append('storage', self.storageValue);
                    formData.append('group', self.groupValue);
                    formData.append('path', self.pathValue);
                });

                this.on('removedfile', file => {
                    if (file.hasOwnProperty('data') && file.data.hasOwnProperty('id')) {
                        let removeItem = dropzoneElement.querySelector(`.files-${file.data.id}`);
                        if (removeItem && removeItem.parentNode) {
                            removeItem.parentNode.removeChild(removeItem);
                        }
                        fetch(self.prefix('/systems/files/') + file.data.id, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-Token': document.head.querySelector('meta[name="csrf_token"]').content
                            }
                        }).then();
                    }
                });
            },

            success: (file, response) => {
                console.log('Video upload success:', response);

                if (response && response.id) {
                    // Create video record from attachment
                    this.createVideoFromAttachment(response.id);
                }
            },

            error: (file, response) => {
                console.error('Video upload error:', response);
                this.toast(this.errorTypeValue);
            }
        });
    }

    /**
     * Create video from attachment
     */
    async createVideoFromAttachment(attachmentId) {
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
            });

            const data = await response.json();

            if (data.success) {
                // Select the newly created video
                this.selectVideo(data.video);
                // Refresh recent videos
                this.loadRecentVideos();
                this.toast('Video uploaded successfully');
            } else {
                this.toast('Failed to create video: ' + data.message);
            }
        } catch (error) {
            console.error('Failed to create video from attachment:', error);
            this.toast('Failed to create video from attachment');
        }
    }

    /**
     * Trigger file input click
     */
    triggerFileInput(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (this.dropZone && this.dropZone.hiddenFileInput) {
            this.dropZone.hiddenFileInput.click();
        }
    }

    /**
     * Format duration
     */
    formatDuration(seconds) {
        if (!seconds) return '00:00';
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hours > 0) {
            return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Get status color
     */
    getStatusColor(status) {
        switch (status) {
            case 'completed': return 'success';
            case 'processing': return 'warning';
            case 'failed': return 'danger';
            default: return 'secondary';
        }
    }

    /**
     * Show toast notification
     */
    toast(message, type = 'info') {
        // Simple toast implementation - can be enhanced with a proper toast library
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }

}
