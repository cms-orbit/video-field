@component($typeForm, get_defined_vars())
<div 
    data-controller="video-upload-edit"
    data-video-upload-edit-name-value="{{ $name }}"
    data-video-upload-edit-max-file-size-value="{{ $attributes['max_file_size'] ?? 2048 }}"
    data-video-upload-edit-chunk-size-value="{{ $attributes['chunk_size'] ?? 1 }}"
    data-video-upload-edit-max-files-value="{{ $attributes['max_files'] ?? 1 }}"
    data-video-upload-edit-auto-process-value="{{ $attributes['auto_process'] ? 'true' : 'false' }}"
    data-video-upload-edit-show-progress-value="{{ $attributes['show_progress'] ? 'true' : 'false' }}"
    data-video-upload-edit-multiple-value="{{ $attributes['multiple'] ? 'true' : 'false' }}"
    data-video-upload-edit-allowed-extensions-value="{{ json_encode($attributes['allowed_extensions'] ?? []) }}"
    data-video-upload-edit-profiles-value="{{ json_encode($attributes['profiles'] ?? []) }}"
    data-video-upload-edit-auto-thumbnail-value="{{ $attributes['auto_thumbnail'] ? 'true' : 'false' }}"
    data-video-upload-edit-auto-sprite-value="{{ $attributes['auto_sprite'] ? 'true' : 'false' }}"
    data-video-upload-edit-value="{{ json_encode($value ?? []) }}"
    data-video-upload-edit-upload-url-value="{{ route('api.video.upload.chunk') }}"
    data-video-upload-edit-complete-url-value="{{ route('api.video.upload.complete') }}"
    data-video-upload-edit-cancel-url-value="{{ route('api.video.upload.cancel') }}"
    class="video-upload-field"
>
    <!-- Hidden input to store the actual value -->
    <input 
        type="hidden" 
        name="{{ $name }}" 
        data-video-upload-edit-target="hiddenInput"
        value="{{ json_encode($value ?? []) }}"
    >

    <!-- Upload Area -->
    <div class="video-upload-area" data-video-upload-edit-target="uploadArea">
        <div class="upload-zone" data-video-upload-edit-target="dropZone">
            <div class="upload-icon">
                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4M7 4H17M7 4L6 22H18L17 4M10 8V18M14 8V18">
                    </path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M15 11L12 8L9 11M12 8V16">
                    </path>
                </svg>
            </div>
            <div class="upload-text">
                <p class="text-lg font-medium text-gray-700">
                    {{ $attributes['placeholder'] ?? __('Drop video files here or click to browse') }}
                </p>
                <p class="text-sm text-gray-500">
                    {{ __('Supported formats: :formats', ['formats' => implode(', ', $attributes['allowed_extensions'] ?? [])]) }}
                </p>
                <p class="text-sm text-gray-400">
                    {{ __('Maximum file size: :size MB', ['size' => $attributes['max_file_size'] ?? 2048]) }}
                </p>
            </div>
            <input 
                type="file" 
                data-video-upload-edit-target="fileInput"
                accept="{{ $attributes['accept'] ?? 'video/*' }}"
                {{ $attributes['multiple'] ? 'multiple' : '' }}
                class="hidden"
            >
            <button 
                type="button" 
                data-video-upload-edit-target="browseBtn"
                class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
            >
                {{ __('Choose Files') }}
            </button>
        </div>
    </div>

    <!-- File List -->
    <div class="video-file-list mt-4" data-video-upload-edit-target="fileList" style="display: none;">
        <div class="space-y-2" data-video-upload-edit-target="fileItems">
            <!-- File items will be inserted here -->
        </div>
    </div>

    <!-- Existing Videos Display -->
    <div class="existing-videos mt-4" data-video-upload-edit-target="existingVideos">
        <!-- Existing videos will be displayed here -->
    </div>

    <!-- Help Text -->
    @if(isset($attributes['help']))
        <div class="form-text text-muted mt-2">
            {{ $attributes['help'] }}
        </div>
    @endif

    <!-- Error Messages -->
    <div class="video-upload-errors mt-2" data-video-upload-edit-target="errorContainer" style="display: none;">
        <div class="alert alert-danger" data-video-upload-edit-target="errorMessage"></div>
    </div>
</div>

<!-- File Item Template -->
<template data-video-upload-edit-target="fileTemplate">
    <div class="video-file-item p-4 border border-gray-200 rounded-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="video-file-icon">
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4M4 7H20M19 7L18 20H6L5 7">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M15 11L12 8L9 11M12 8V16">
                        </path>
                    </svg>
                </div>
                <div class="video-file-info">
                    <div class="video-file-name font-medium text-gray-900"></div>
                    <div class="video-file-size text-sm text-gray-500"></div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <div class="video-file-status">
                    <!-- Status badges will be inserted here -->
                </div>
                <button type="button" class="video-file-remove text-red-500 hover:text-red-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Progress Bar -->
        <div class="video-file-progress mt-3" style="display: none;">
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     style="width: 0%"
                     aria-valuenow="0" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            <div class="progress-text text-sm text-gray-600 mt-1"></div>
        </div>

        <!-- Video Preview (after upload) -->
        <div class="video-preview mt-3" style="display: none;">
            <div class="aspect-video bg-gray-100 rounded-lg overflow-hidden">
                <video class="w-full h-full object-cover" controls>
                    <!-- Source will be added dynamically -->
                </video>
            </div>
            <div class="video-details mt-2 text-sm text-gray-600">
                <div class="video-duration"></div>
                <div class="video-resolution"></div>
            </div>
        </div>

        <!-- Processing Status -->
        <div class="processing-status mt-3" style="display: none;">
            <div class="flex items-center space-x-2">
                <div class="processing-spinner">
                    <svg class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" 
                              d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </div>
                <span class="processing-text text-sm text-gray-600">{{ __('Processing video...') }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.video-upload-field {
    font-family: system-ui, -apple-system, sans-serif;
}

.upload-zone {
    @apply border-2 border-dashed border-gray-300 rounded-lg p-8 text-center transition-colors duration-200 cursor-pointer;
}

.upload-zone:hover {
    @apply border-gray-400 bg-gray-50;
}

.upload-zone.dragover {
    @apply border-blue-500 bg-blue-50;
}

.upload-icon {
    @apply mx-auto mb-4;
}

.progress {
    @apply w-full bg-gray-200 rounded-full h-2;
}

.progress-bar {
    @apply bg-blue-500 h-2 rounded-full transition-all duration-300;
}

.progress-bar-striped {
    background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
    background-size: 1rem 1rem;
}

.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

@keyframes progress-bar-stripes {
    0% { background-position: 1rem 0; }
    100% { background-position: 0 0; }
}

.video-file-item {
    @apply transition-all duration-200;
}

.video-file-item:hover {
    @apply shadow-md;
}

.processing-spinner svg {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
@endcomponent 