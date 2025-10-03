@component($typeForm, get_defined_vars())
    <div
        data-controller="video"
        data-video-without-upload-value="{{ $withoutUpload ? 'true' : 'false' }}"
        data-video-without-exists-value="{{ $withoutExists ? 'true' : 'false' }}"
        data-video-placeholder-value="{{ $placeholder }}"
        data-video-max-results-value="{{ $maxResults }}"
        data-video-ajax-url-value="{{ route('orbit-videos.api.search') }}"
        data-video-recent-url-value="{{ route('orbit-videos.api.recent') }}"
        data-video-group-value="{{ $group }}"
        data-video-name-value="{{ $name }}"
        data-video-storage-value="{{ $storage ?? 'public' }}"
        data-video-path-value="{{ $path ?? '' }}"
        data-video-count-value="{{ $count ?? 1 }}"
        data-video-size-value="{{ $size ?? 500 }}"
        data-video-upload-url-value="{{ $uploadUrl ?? '/settings/systems/files' }}"
        data-video-sort-url-value="{{ $sortUrl ?? '/settings/systems/files/sort' }}"
        data-video-error-size-value="{{ $errorSize ?? 'File ":name" is too large to upload (max 500MB)' }}"
        data-video-error-type-value="{{ $errorType ?? 'The attached file must be a video' }}"
    >
        <div class="video-field-container">
            <!-- Selected Video Info -->
            <div data-video-target="selectedVideoInfo" style="display: none;" class="mb-4">
                <!-- Will be populated by JavaScript -->
            </div>

            <!-- Search Section -->
            @if(!$withoutExists)
                <div class="mb-4" data-video-target="searchSection">
                    <label class="form-label">{{ __('Select Existing Video') }}</label>

                    <!-- Search Input -->
                    <div class="mb-3">
                        <input
                            type="text"
                            class="form-control"
                            placeholder="{{ $placeholder ?? __('Search videos...') }}"
                            data-video-target="search"
                            data-action="input->video#searchVideos"
                        />
                    </div>

                    <!-- Video Select Dropdown -->
                    <div class="mb-3">
                        <select
                            class="form-select"
                            data-video-target="videoSelect"
                            data-action="change->video#onVideoSelect"
                        >
                            <option value="">{{ __('Choose a video...') }}</option>
                        </select>
                    </div>
                </div>
            @endif

            <!-- Upload Section -->
            @if(!$withoutUpload)
                <div class="mb-4" data-video-target="uploadSection">
                    <label class="form-label">{{ __('Upload New Video') }}</label>
                    <div class="upload-area border border-dashed rounded p-4 text-center" style="min-height: 120px; cursor: pointer;">
                        <div class="upload-content">
                            <i class="bs bs-cloud-arrow-up h3 text-muted mb-2"></i>
                            <p class="text-muted mb-2">{{ __('Click to upload video file') }}</p>
                            <small class="text-muted">{{ __('Supported formats: MP4, AVI, MOV, etc. Max size: 500MB') }}</small>
                        </div>
                    </div>
                </div>
            @endif

            <input type="hidden" name="{{ $name }}" data-video-target="hiddenInput" value="{{ $value ? json_encode($value) : '' }}">
        </div>
    </div>
@endcomponent
