# Video Player 사용 가이드

## 개요
CMS Orbit Video Player는 DASH, HLS, Progressive 스트리밍을 지원하는 Vue.js 기반의 비디오 플레이어 컴포넌트입니다.

## 주요 기능
- **다중 스트리밍 형식 지원**: DASH → HLS → Progressive 순서로 자동 폴백
- **동적 스크립트 로딩**: HLS.js 및 Dash.js를 필요 시 동적으로 로드
- **인코딩 상태 표시**: 비디오 처리 진행률을 실시간으로 표시
- **에러 처리**: 비디오 로드 실패 시 사용자 친화적인 에러 메시지 표시
- **자동 정리**: 컴포넌트 언마운트 시 플레이어 리소스 자동 정리

## 설치 및 설정

### 1. Alias 설정 확인
`resources/js/theme/index.js`에 다음 alias가 설정되어 있는지 확인하세요:

```javascript
alias: {
    '@orbit/video': '/path/to/packages/cms-orbit-video/resources/js',
}
```

### 2. 라이브러리 파일 확인
다음 파일들이 public 디렉토리에 있는지 확인하세요:
- `/vendor/cms-orbit/video/js/hls.js`
- `/vendor/cms-orbit/video/js/dashjs.js`

## 사용 방법

### 기본 사용법

```vue
<template>
    <Player :video="videoObject" />
</template>

<script setup>
import Player from '@orbit/video/Player.vue';

const videoObject = {
    id: 1,
    title: 'Sample Video',
    // ... 기타 속성
};
</script>
```

### Props

#### 1. video (Object, optional)
비디오 객체를 전달합니다. 객체에 `id` 속성이 있으면 자동으로 추출됩니다.

```vue
<Player :video="announcement.featured_video" />
```

#### 2. videoId (Number, optional)
비디오 ID만 직접 전달합니다.

```vue
<Player :video-id="123" />
```

#### 3. debug (Boolean, optional, default: false)
디버그 모드를 활성화합니다. `true`로 설정하면:
- 상세한 콘솔 로그 출력
- 비디오 플레이어 위에 현재 화질 정보 표시 (해상도 및 비트레이트)
- 화질 전환 이벤트 로깅
- 매니페스트 파싱 정보 출력

```vue
<Player :video="video" :debug="true" />
```

**중요**: `video`와 `videoId` 중 하나는 반드시 제공되어야 합니다. 둘 다 제공되면 `video.id`가 우선순위를 가집니다.

### 사용 예시

#### 예시 1: 비디오 객체 전달
```vue
<template>
    <div class="announcement-detail">
        <h1>{{ announcement.title }}</h1>
        <Player :video="announcement.featured_video" />
        <div v-html="announcement.content"></div>
    </div>
</template>

<script setup>
import Player from '@orbit/video/Player.vue';
import { defineProps } from 'vue';

const props = defineProps({
    announcement: Object,
});
</script>
```

#### 예시 2: 비디오 ID만 전달
```vue
<template>
    <div class="video-section">
        <Player :video-id="introVideoId" />
    </div>
</template>

<script setup>
import Player from '@orbit/video/Player.vue';
import { ref } from 'vue';

const introVideoId = ref(456);
</script>
```

#### 예시 3: 조건부 렌더링
```vue
<template>
    <div>
        <Player 
            v-if="announcement.intro_video" 
            :video-id="announcement.intro_video.id" 
        />
    </div>
</template>

<script setup>
import Player from '@orbit/video/Player.vue';
import { defineProps } from 'vue';

const props = defineProps({
    announcement: Object,
});
</script>
```

#### 예시 4: 디버그 모드
```vue
<template>
    <div>
        <!-- 개발 환경에서만 디버그 모드 활성화 -->
        <Player 
            :video="video" 
            :debug="import.meta.env.DEV" 
        />
        
        <!-- 또는 항상 디버그 모드 -->
        <Player 
            :video="video" 
            :debug="true" 
        />
    </div>
</template>

<script setup>
import Player from '@orbit/video/Player.vue';

const video = { id: 1, title: 'Test Video' };
</script>
```

디버그 모드에서는 콘솔에 다음과 같은 정보가 출력됩니다:
```
[Video Player] Loading video data for ID: 1
[Video Player] Video data loaded: { id: 1, status: 'completed', ... }
[Video Player] Initializing player for video: 1 Test Video
[Video Player] Initializing DASH player with URL: https://...
[Video Player] DASH stream initialized
[Video Player] DASH manifest parsed, available levels: 3
  Level 0: 1920x1080 @ 8000kbps
  Level 1: 1280x720 @ 4000kbps
  Level 2: 640x480 @ 2000kbps
[Video Player] DASH initial quality: { width: 1920, height: 1080, ... }
[Video Player] DASH quality switched to: { width: 1280, height: 720, ... }
```

## 스트리밍 우선순위

Player 컴포넌트는 다음 순서로 비디오 스트리밍을 시도합니다:

1. **DASH (Dynamic Adaptive Streaming over HTTP)**
   - 최신 스트리밍 표준
   - 가장 효율적인 적응형 비트레이트 스트리밍

2. **HLS (HTTP Live Streaming)**
   - Apple 기기에서 네이티브 지원
   - 광범위한 브라우저 호환성

3. **Progressive MP4**
   - 전통적인 비디오 재생 방식
   - 모든 브라우저에서 지원

각 형식이 사용 불가능하거나 실패하면 자동으로 다음 형식으로 폴백됩니다.

## 플레이어 상태

### 1. 로딩 중
비디오 데이터를 로드하는 동안 표시됩니다.

```
┌─────────────────────────┐
│   ⟳ Loading video...   │
└─────────────────────────┘
```

### 2. 인코딩 중
비디오가 아직 처리 중일 때 진행률과 함께 표시됩니다.

```
┌─────────────────────────────┐
│  ⏳ Video is being processed │
│  [████████░░] 75% complete  │
└─────────────────────────────┘
```

### 3. 재생 가능
비디오가 완료되어 재생 가능한 상태입니다.

```
┌─────────────────────────────┐
│  ▶ Video Player             │
│  [Controls]                 │
└─────────────────────────────┘
```

### 4. 에러 상태
비디오 로드 실패 시 에러 메시지가 표시됩니다.

```
┌─────────────────────────────┐
│  ⚠️ Failed to load video    │
│  Error message here         │
└─────────────────────────────┘
```

## API 엔드포인트

Player 컴포넌트는 다음 API를 사용합니다:

```
GET /api/orbit-videos/{id}
```

### 응답 형식

```json
{
    "id": 1,
    "title": "Sample Video",
    "duration": 120,
    "status": "completed",
    "thumbnail_url": "https://example.com/thumbnail.jpg",
    "encoding_progress": 100,
    "streaming": {
        "dash": "https://example.com/video.mpd",
        "hls": "https://example.com/video.m3u8",
        "progressive": "https://example.com/video.mp4"
    },
    "profiles": [
        {
            "profile": "1080p",
            "encoded": true,
            "resolution": "1920x1080",
            "quality_label": "FHD",
            "url": "https://example.com/1080p.mp4"
        }
    ]
}
```

## 스타일링

Player 컴포넌트는 scoped 스타일을 사용하며, 상위 컨테이너의 크기에 자동으로 맞춰집니다.

### 반응형 크기 조절

컴포넌트는 상위 컨테이너의 너비와 높이를 모두 100% 차지하며, 비디오는 `object-fit: contain` 방식으로 표시됩니다.

```vue
<template>
    <!-- 고정 크기 -->
    <div class="w-64 h-36">
        <Player :video="video" />
    </div>
    
    <!-- 작은 썸네일 -->
    <div class="w-20 h-24">
        <Player :video="video" />
    </div>
    
    <!-- 전체 너비, 고정 높이 -->
    <div class="w-full h-96">
        <Player :video="video" />
    </div>
    
    <!-- 16:9 비율 -->
    <div class="w-full aspect-video">
        <Player :video="video" />
    </div>
</template>
```

### CSS 클래스

- `.video-player-wrapper` - 최상위 컨테이너 (100% 너비/높이, flexbox 중앙 정렬)
- `.video-player-loading` - 로딩 상태
- `.video-player-error` - 에러 상태
- `.video-player-processing` - 인코딩 중 상태
- `.video-player-container` - 비디오 컨테이너
- `.video-element` - 실제 video 엘리먼트 (contain 방식)

### 반응형 텍스트

모든 텍스트와 아이콘은 `clamp()` CSS 함수를 사용하여 컨테이너 크기에 따라 자동으로 조절됩니다.

### 커스텀 스타일링 예시

```vue
<template>
    <div class="custom-video-container">
        <Player :video="video" />
    </div>
</template>

<style scoped>
/* 16:9 비율 유지 */
.custom-video-container {
    width: 100%;
    max-width: 800px;
    aspect-ratio: 16 / 9;
    margin: 0 auto;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* 또는 고정 크기 */
.custom-video-container {
    width: 640px;
    height: 360px;
    border-radius: 12px;
    overflow: hidden;
}
</style>
```

## 문제 해결

### 비디오가 재생되지 않음
1. 비디오 상태가 'completed'인지 확인
2. 브라우저 콘솔에서 네트워크 에러 확인
3. 스트리밍 URL이 올바른지 확인

### 스크립트 로드 실패
1. `/vendor/cms-orbit/video/js/` 경로에 파일이 있는지 확인
2. 브라우저 콘솔에서 404 에러 확인
3. public 디렉토리 심볼릭 링크 확인: `php artisan storage:link`

### 인코딩이 진행되지 않음
1. 큐 워커가 실행 중인지 확인: `php artisan queue:work`
2. 비디오 인코딩 로그 확인
3. 비디오 프로파일 설정 확인

## 고급 기능

### 동적 스크립트 로딩 커스터마이징

Player 컴포넌트는 HLS.js와 Dash.js를 동적으로 로드합니다. 다른 경로에서 로드하려면 컴포넌트를 수정해야 합니다:

```javascript
// Player.vue의 loadPlayerScripts 함수 수정
const loadPlayerScripts = async () => {
    await Promise.all([
        loadScript('/custom/path/hls.js'),
        loadScript('/custom/path/dashjs.js'),
    ]);
};
```

### 에러 핸들링 커스터마이징

컴포넌트를 래핑하여 커스텀 에러 처리를 추가할 수 있습니다:

```vue
<template>
    <div>
        <Player 
            :video="video" 
            @error="handleVideoError"
        />
    </div>
</template>

<script setup>
import Player from '@orbit/video/Player.vue';

const handleVideoError = (error) => {
    console.error('Video error:', error);
    // 커스텀 에러 처리 로직
};
</script>
```

## 언어 지원

Player 컴포넌트는 다국어를 지원합니다. 다음 키들이 언어팩에 정의되어 있어야 합니다:

```json
{
    "Loading video...": "비디오 로드 중...",
    "Failed to load video": "비디오 로드 실패",
    "Video is being processed": "비디오 처리 중",
    "complete": "완료",
    "Your browser does not support video playback.": "브라우저가 비디오 재생을 지원하지 않습니다.",
    "No video available": "사용 가능한 비디오가 없습니다",
    "No video ID provided": "비디오 ID가 제공되지 않았습니다",
    "Failed to load video data": "비디오 데이터 로드 실패",
    "No compatible video format available": "재생 가능한 비디오 형식이 없습니다",
    "Unknown video error": "알 수 없는 비디오 오류",
    "Video playback error: ": "비디오 재생 오류: "
}
```

## 라이센스
이 컴포넌트는 CMS Orbit 프로젝트의 일부입니다.

