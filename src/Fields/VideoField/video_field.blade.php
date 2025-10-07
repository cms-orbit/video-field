@component($typeForm, get_defined_vars())
    @php
        // Prepare initial value from existing field value
        $initialValue = '';
        if ($value) {
            if (is_string($value)) {
                $initialValue = $value;
            } else {
                $initialValue = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
        }
    @endphp

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
        data-video-size-value="{{ $size ?? 0 }}"
        data-video-upload-url-value="{{ $uploadUrl ?? '/settings/systems/files' }}"
        data-video-sort-url-value="{{ $sortUrl ?? '/settings/systems/files/sort' }}"
        data-video-error-size-value="{{ $errorSize ?? __('File ":name" is too large. Maximum size: :size MB') }}"
        data-video-error-type-value="{{ $errorType ?? 'The attached file must be a video' }}"
        data-video-initial-value-value="{!! htmlspecialchars($initialValue, ENT_QUOTES, 'UTF-8') !!}"
    >
        <!-- Vue component will be mounted here -->
    </div>
@endcomponent
