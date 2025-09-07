@if($video->exists && $video->getAttribute('status') === 'completed')
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Video Preview') }}</h3>

        <div class="flex gap-x-3">
            <!-- HLS Stream Test -->
            @if($video->getBestHlsUrl())
                <div class="border rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-2">{{ __('HLS Stream Test') }}</h4>
                    <div class="bg-gray-50 rounded p-3 mb-3">
                        <code class="text-sm text-gray-600 break-all">{{ $video->getBestHlsUrl() }}</code>
                    </div>
                    <div class="video-player-container">
                        <video
                            id="hls-player"
                            controls
                            class="w-full max-w-2xl"
                            style="max-height: 400px;"
                            preload="metadata">
                            <source src="{{ $video->getBestHlsUrl() }}" type="application/x-mpegURL">
                            {{ __('Your browser does not support HLS video playback.') }}
                        </video>
                    </div>
                </div>
            @endif

            <!-- DASH Stream Test -->
            @if($video->getBestDashUrl())
                <div class="border rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-2">{{ __('DASH Stream Test') }}</h4>
                    <div class="bg-gray-50 rounded p-3 mb-3">
                        <code class="text-sm text-gray-600 break-all">{{ $video->getBestDashUrl() }}</code>
                    </div>
                    <div class="video-player-container">
                        <video
                            id="dash-player"
                            controls
                            class="w-full max-w-2xl"
                            style="max-height: 400px;"
                            preload="metadata">
                            <source src="{{ $video->getBestDashUrl() }}" type="application/dash+xml">
                            {{ __('Your browser does not support DASH video playback.') }}
                        </video>
                    </div>
                </div>
            @endif

            <!-- No streams available message -->
            @if(!$video->getBestHlsUrl() && !$video->getBestDashUrl())
                <div class="text-center py-8 text-gray-500">
                    <div class="text-4xl mb-2">📹</div>
                    <p>{{ __('No streaming formats available for this video.') }}</p>
                    <p class="text-sm mt-1">{{ __('Please wait for encoding to complete or check the encoding status.') }}</p>
                </div>
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        .video-player-container {
            position: relative;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        .video-player-container video {
            width: 100%;
            height: auto;
        }
    </style>
    @endpush

    @push('scripts')
    <!-- HLS.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <!-- Dash.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/dashjs@latest/dist/dash.all.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // HLS.js for HLS support
            if (typeof Hls !== 'undefined' && document.getElementById('hls-player')) {
                const hlsPlayer = document.getElementById('hls-player');
                const hlsUrl = hlsPlayer.querySelector('source').src;

                if (Hls.isSupported()) {
                    const hls = new Hls({
                        debug: false, // 디버그 모드 비활성화
                        enableWorker: true,
                        lowLatencyMode: false,
                        backBufferLength: 30, // 백버퍼 길이 감소
                        // ABR (Adaptive Bitrate) 설정
                        abrEwmaFastLive: 3.0,
                        abrEwmaSlowLive: 9.0,
                        abrEwmaFastVoD: 3.0,
                        abrEwmaSlowVoD: 9.0,
                        abrMaxWithRealBitrate: false,
                        abrBandWidthFactor: 0.95,
                        abrBandWidthUpFactor: 0.7,
                        // 자동 화질 전환 활성화
                        enableSoftwareAES: true,
                        // 대역폭 측정 설정
                        bandwidthEstimate: 5000000, // 5Mbps 초기 추정치
                        // 화질 전환 임계값
                        maxStarvationDelay: 4,
                        maxLoadingDelay: 4,
                        // 추가 설정
                        startLevel: -1, // 자동 레벨 선택
                        capLevelToPlayerSize: true, // 플레이어 크기에 맞춰 레벨 제한
                        testBandwidth: false, // 대역폭 테스트 비활성화
                        // 버퍼 관리 설정
                        maxBufferLength: 30, // 최대 버퍼 길이 (초)
                        maxBufferSize: 60 * 1000 * 1000, // 최대 버퍼 크기 (60MB)
                        maxBufferHole: 0.1, // 최대 버퍼 홀 허용치
                        // 에러 처리 설정
                        ignorePlaylistParsingErrors: true, // 플레이리스트 파싱 에러 무시
                        appendErrorMaxRetry: 3, // 버퍼 추가 에러 최대 재시도 횟수
                        // SourceBuffer 관리 개선
                        stretchShortVideoTrack: false, // 짧은 비디오 트랙 늘리기 비활성화
                        maxAudioFramesDrift: 1, // 오디오 프레임 드리프트 최대값
                        forceKeyFrameOnDiscontinuity: true, // 불연속성에서 키프레임 강제
                        // 버퍼 플러시 설정
                        liveBackBufferLength: 0, // 라이브 백버퍼 길이
                        liveMaxLatencyDurationCount: Infinity, // 라이브 최대 지연 시간
                        liveSyncDurationCount: 3, // 라이브 동기화 지속 시간
                        liveSyncOnStallIncrease: 1, // 스톨 증가 시 라이브 동기화
                        // 추가 안정성 설정
                        enableDateRangeMetadataCues: false, // 날짜 범위 메타데이터 큐 비활성화
                        enableEmsgMetadataCues: false, // EMSG 메타데이터 큐 비활성화
                        enableID3MetadataCues: false, // ID3 메타데이터 큐 비활성화
                        enableInterstitialPlayback: false, // 간질 재생 비활성화
                        useMediaCapabilities: false, // 미디어 기능 사용 비활성화
                        // 에러 복구 설정
                        fragLoadingTimeOut: 20000, // 프래그먼트 로딩 타임아웃
                        manifestLoadingTimeOut: 10000, // 매니페스트 로딩 타임아웃
                        manifestLoadingMaxRetry: 4, // 매니페스트 로딩 최대 재시도
                        manifestLoadingRetryDelay: 1000, // 매니페스트 로딩 재시도 지연
                        levelLoadingTimeOut: 10000, // 레벨 로딩 타임아웃
                        levelLoadingMaxRetry: 4, // 레벨 로딩 최대 재시도
                        levelLoadingRetryDelay: 1000, // 레벨 로딩 재시도 지연
                        fragLoadingMaxRetry: 6, // 프래그먼트 로딩 최대 재시도
                        fragLoadingRetryDelay: 1000 // 프래그먼트 로딩 재시도 지연
                    });
                    hls.loadSource(hlsUrl);
                    hls.attachMedia(hlsPlayer);

                    hls.on(Hls.Events.ERROR, function (event, data) {
                        // SourceBuffer 관련 에러는 무시 (재생에는 영향 없음)
                        if (data.details === 'bufferAddCodecError' ||
                            data.details === 'bufferAppendError' ||
                            data.details === 'bufferFullError' ||
                            data.details === 'bufferStalledError') {
                            return;
                        }

                        // 치명적인 에러만 로깅
                        if (data.fatal) {
                            console.error('HLS Fatal Error:', data);
                        }
                    });

                    // 화질 전환 이벤트 로깅
                    hls.on(Hls.Events.LEVEL_SWITCHED, function (event, data) {
                        console.log('Quality switched to level:', data.level);
                    });

                    // 대역폭 변화 이벤트 로깅
                    hls.on(Hls.Events.BANDWIDTH_ESTIMATE, function (event, data) {
                        console.log('Bandwidth estimate:', Math.round(data.bandwidth / 1000) + ' kbps');
                    });

                    // 버퍼 상태 이벤트
                    hls.on(Hls.Events.BUFFER_FLUSHED, function (event, data) {
                        console.log('Buffer flushed:', data);
                    });

                    // 미디어 연결 이벤트
                    hls.on(Hls.Events.MEDIA_ATTACHED, function (event, data) {
                        console.log('Media attached:', data);
                    });
                } else if (hlsPlayer.canPlayType('application/vnd.apple.mpegurl')) {
                    // Native HLS support (Safari)
                    hlsPlayer.src = hlsUrl;
                }
            }

            // Dash.js for DASH support
            if (typeof dashjs !== 'undefined' && document.getElementById('dash-player')) {
                const dashPlayer = document.getElementById('dash-player');
                const dashUrl = dashPlayer.querySelector('source').src;

                const dash = dashjs.MediaPlayer().create();

                // ABR 설정 (지원되는 설정만 사용)
                dash.updateSettings({
                    'debug': {
                        'logLevel': dashjs.Debug.LOG_LEVEL_NONE
                    },
                    'streaming': {
                        'abr': {
                            'autoSwitchBitrate': {
                                'video': true,
                                'audio': true
                            },
                            'useDeadTimeLatency': true,
                            'usePixelRatioInLimitBitrateByPortal': true
                        },
                        'buffer': {
                            'bufferTimeAtTopQuality': 30,
                            'bufferTimeAtTopQualityLongForm': 60,
                            'longFormContentDurationThreshold': 600,
                            'stableBufferTime': 12
                        }
                    }
                });

                dash.initialize(dashPlayer, dashUrl, false);

                dash.on('error', function(e) {
                    console.error('DASH Error:', e);
                });

                // 화질 전환 이벤트 로깅
                dash.on('qualityChangeRequested', function(e) {
                    console.log('DASH Quality change requested:', e);
                });
            }
        });
    </script>
    @endpush
@else
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="text-center py-8 text-gray-500">
            <div class="text-4xl mb-2">⏳</div>
            <p>{{ __('Video is not ready for playback yet.') }}</p>
            <p class="text-sm mt-1">{{ __('Please wait for encoding to complete.') }}</p>
        </div>
    </div>
@endif
