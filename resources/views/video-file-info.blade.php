@php
    $video = $video ?? null;
    $originalFile = $video?->originalFile;
@endphp

<div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Video File Information') }}</h3>

    @if($originalFile)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- File Information -->
            <div class="space-y-3">
                <h4 class="font-medium text-gray-700">{{ __('File Details') }}</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('MIME Type') }}:</span>
                        <span class="font-medium">{{ $originalFile->mime }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('File Size') }}:</span>
                        <span class="font-medium">{{ number_format($originalFile->size / 1024 / 1024, 2) }} MB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Uploaded At') }}:</span>
                        <span class="font-medium">{{ $originalFile->created_at->format('Y-m-d H:i:s') }}</span>
                    </div>
                </div>
            </div>

            <!-- Video Properties -->
            <div class="space-y-3">
                <h4 class="font-medium text-gray-700">{{ __('Video Properties') }}</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Duration') }}:</span>
                        <span class="font-medium">{{ $video->getReadableDuration() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Resolution') }}:</span>
                        <span class="font-medium">{{ $video->original_width }}x{{ $video->original_height }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Frame Rate') }}:</span>
                        <span class="font-medium">{{ $video->original_framerate }} fps</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Bitrate') }}:</span>
                        <span class="font-medium">{{ number_format($video->original_bitrate / 1000) }} kbps</span>
                    </div>
                </div>
            </div>

            <!-- Processing Status -->
            <div class="space-y-3">
                <h4 class="font-medium text-gray-700">{{ __('Processing Status') }}</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Status') }}:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($video->status === 'completed') bg-green-100 text-green-800
                            @elseif($video->status === 'processing') bg-blue-100 text-blue-800
                            @elseif($video->status === 'failed') bg-red-100 text-red-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($video->status) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Progress') }}:</span>
                        <span class="font-medium">{{ $video->getEncodingProgress() }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Profiles') }}:</span>
                        <span class="font-medium">{{ $video->profiles()->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Encoded') }}:</span>
                        <span class="font-medium">{{ $video->profiles()->where('encoded', true)->count() }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Thumbnail & Sprite Information -->
            <div class="space-y-3">
                <h4 class="font-medium text-gray-700">{{ __('Media Assets') }}</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Has Thumbnail') }}:</span>
                        <span class="font-medium">
                            @if($video->hasThumbnail())
                                <span class="text-green-600">✓ {{ __('Yes') }}</span>
                            @else
                                <span class="text-red-600">✗ {{ __('No') }}</span>
                            @endif
                        </span>
                    </div>
                    @if($video->getAttribute('thumbnail_path'))
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('Thumbnail Path') }}:</span>
                            <code class="text-xs bg-gray-100 px-1 rounded">{{ $video->getAttribute('thumbnail_path') }}</code>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Has Sprite') }}:</span>
                        <span class="font-medium">
                            @if($video->hasSprite())
                                <span class="text-green-600">✓ {{ __('Yes') }}</span>
                            @else
                                <span class="text-red-600">✗ {{ __('No') }}</span>
                            @endif
                        </span>
                    </div>
                    @if($video->getAttribute('scrubbing_sprite_path'))
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('Sprite Path') }}:</span>
                            <code class="text-xs bg-gray-100 px-1 rounded">{{ $video->getAttribute('scrubbing_sprite_path') }}</code>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Streaming Information -->
            <div class="space-y-3">
                <h4 class="font-medium text-gray-700">{{ __('Streaming') }}</h4>
                <div class="space-y-2 text-sm">
                    @if($video->getAttribute('hls_manifest_path'))
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('HLS Manifest') }}:</span>
                            <code class="text-xs bg-gray-100 px-1 rounded">{{ $video->getAttribute('hls_manifest_path') }}</code>
                        </div>
                    @endif
                    @if($video->getAttribute('dash_manifest_path'))
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('DASH Manifest') }}:</span>
                            <code class="text-xs bg-gray-100 px-1 rounded">{{ $video->getAttribute('dash_manifest_path') }}</code>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Supports ABR') }}:</span>
                        <span class="font-medium">
                            @if($video->supportsAbr())
                                <span class="text-green-600">✓ {{ __('Yes') }}</span>
                            @else
                                <span class="text-red-600">✗ {{ __('No') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('ABR Profiles') }}:</span>
                        <span class="font-medium">{{ count($video->getAvailableProfiles() ?? []) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metadata Information -->
        @if($video->getAttribute('meta_data'))
            <div class="mt-6 pt-4 border-t border-gray-200">
                <h4 class="font-medium text-gray-700 mb-2">{{ __('Metadata') }}</h4>
                <div class="bg-gray-50 rounded-md p-3">
                    <pre class="text-xs text-gray-600 overflow-x-auto"><code>{{ json_encode($video->getAttribute('meta_data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                </div>
            </div>
        @endif
        </div>

        <!-- Progress Bar -->
        @if($video->status === 'processing')
            <div class="mt-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>{{ __('Encoding Progress') }}</span>
                    <span>{{ $video->getEncodingProgress() }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                         style="width: {{ $video->getEncodingProgress() }}%"></div>
                </div>
            </div>
        @endif
    @else
        <div class="text-center py-8">
            <div class="text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('No Video File') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('No original video file has been uploaded yet.') }}</p>
            </div>
        </div>
    @endif
