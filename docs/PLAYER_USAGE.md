# Video Player 사용 가이드

## 기본 사용법

```vue
<script setup>
import Player from '@orbit/video/Player.vue';
</script>

<template>
    <Player :video-id="123" />
</template>
```

## Props

### 비디오 소스
- `video`: 비디오 객체 (선택)
- `videoId`: 비디오 ID (선택)

### 재생 옵션
- `autoplay`: 자동 재생 (기본값: `false`)
- `loop`: 반복 재생 (기본값: `false`)
- `muted`: 음소거 (기본값: `false`)
- `playsinline`: 인라인 재생 (기본값: `true`)
- `preload`: 미리 로드 방법 - `'none'`, `'metadata'`, `'auto'` (기본값: `'metadata'`)

### UI 옵션
- `useNativeControls`: 네이티브 컨트롤 사용 (기본값: `false`)
- `showQualitySelector`: 화질 선택 표시 (기본값: `true`)
- `showDownload`: 다운로드 버튼 표시 (기본값: `false`)
- `showTitle`: 제목 오버레이 표시 (기본값: `false`)
- `showDescription`: 설명 오버레이 표시 (기본값: `false`)

### 기타
- `debug`: 디버그 모드 (기본값: `false`)

## 키보드 단축키

플레이어에 포커스가 있을 때 다음 단축키를 사용할 수 있습니다:

- **스페이스바 / K**: 재생/일시정지
- **← (왼쪽 방향키)**: 10초 되감기
- **→ (오른쪽 방향키)**: 10초 빨리감기
- **↑ (위쪽 방향키)**: 볼륨 증가 (10%)
- **↓ (아래쪽 방향키)**: 볼륨 감소 (10%)
- **M**: 음소거 토글
- **F**: 전체화면 토글

## 마우스 조작

### 클릭 기능
- **화면 클릭** (좌/중앙/우 영역 모두): 재생/정지 토글

### 더블클릭 기능
- **좌측 30% 영역 더블클릭**: 10초 되감기
- **우측 30% 영역 더블클릭**: 10초 빨리감기

### 마우스 오버 컨트롤
마우스를 비디오 위로 올리면 오버레이 컨트롤 버튼이 화면 중앙에 표시됩니다:
- **좌측 버튼**: 30초 되감기
- **중앙 버튼**: 재생/정지 토글
- **우측 버튼**: 30초 빨리감기

재생 중일 때는 마우스를 움직이지 않으면 3초 후 자동으로 사라집니다.

## 커스텀 액션 추가

플레이어 컨트롤에 커스텀 버튼을 추가할 수 있습니다. 두 가지 문법을 모두 지원합니다:

### 방법 1: 단축 문법 (권장)
```vue
<template>
    <Player :video-id="videoId">
        <template #actions="{ videoData, player, isPlaying }">
            <!-- videoData: 비디오 정보 객체 -->
            <!-- player: 플레이어 인스턴스 (HLS/DASH/Progressive) -->
            <!-- isPlaying: 재생 중 여부 (boolean) -->
            
            <button 
                @click="handleCustomAction"
                class="control-button"
            >
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            </button>
        </template>
    </Player>
</template>

<script setup>
const handleCustomAction = () => {
    console.log('Custom action!');
};
</script>
```

### 방법 2: v-slot 문법
```vue
<template>
    <Player :video-id="videoId">
        <template v-slot:actions="{ videoData, player, isPlaying }">
            <!-- 동일한 내용 -->
        </template>
    </Player>
</template>
```

### Scoped Slot Props

#### videoData
비디오 정보 객체:
```javascript
{
    id: 1,
    title: "비디오 제목",
    description: "비디오 설명",
    thumbnail_url: "...",
    sprite_url: "...",
    sprite_metadata: { ... },
    streaming: {
        hls: "...",
        dash: "...",
        progressive: "..."
    },
    profiles: [ ... ],
    status: "completed"
}
```

#### player
플레이어 인스턴스 (HLS.js, DASH.js, 또는 null):
```javascript
// HLS.js 사용 시
if (player.levels) {
    console.log('사용 가능한 화질:', player.levels);
}

// DASH.js 사용 시
if (player.getBitrateInfoListFor) {
    const qualities = player.getBitrateInfoListFor('video');
}
```

#### isPlaying
현재 재생 중인지 여부 (boolean)

## 예제

### 1. 자동 재생 + 다운로드 버튼

```vue
<Player 
    :video-id="123"
    :autoplay="true"
    :show-download="true"
/>
```

### 2. 제목/설명 오버레이 표시

```vue
<Player 
    :video-id="123"
    :show-title="true"
    :show-description="true"
/>
```

### 3. 네이티브 컨트롤 사용

```vue
<Player 
    :video-id="123"
    :use-native-controls="true"
/>
```

### 4. 좋아요 버튼 추가

```vue
<template>
    <Player :video-id="videoId">
        <template #actions="{ videoData, isPlaying }">
            <button 
                @click="toggleLike"
                class="control-button"
                :class="{ liked: isLiked }"
            >
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
            </button>
        </template>
    </Player>
</template>

<script setup>
import { ref } from 'vue';

const videoId = ref(123);
const isLiked = ref(false);

const toggleLike = () => {
    isLiked.value = !isLiked.value;
    // API 호출 등
};
</script>

<style scoped>
.control-button {
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
    border-radius: 4px;
}

.control-button:hover {
    background: rgba(255, 255, 255, 0.1);
}

.control-button.liked {
    color: #ef4444;
}

.control-button svg {
    width: 20px;
    height: 20px;
    fill: currentColor;
}
</style>
```

### 5. 공유 버튼 추가

```vue
<template>
    <Player :video-id="videoId">
        <template #actions="{ videoData }">
            <div class="share-control">
                <button @click="toggleShareMenu" class="control-button">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>
                    </svg>
                </button>
                <div v-if="showShareMenu" class="share-dropdown">
                    <button @click="shareToTwitter(videoData)">Twitter</button>
                    <button @click="shareToFacebook(videoData)">Facebook</button>
                    <button @click="copyLink(videoData)">링크 복사</button>
                </div>
            </div>
        </template>
    </Player>
</template>

<script setup>
import { ref } from 'vue';

const videoId = ref(123);
const showShareMenu = ref(false);

const toggleShareMenu = () => {
    showShareMenu.value = !showShareMenu.value;
};

const shareToTwitter = (videoData) => {
    const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(videoData.title)}&url=${window.location.href}`;
    window.open(url, '_blank');
};

const shareToFacebook = (videoData) => {
    const url = `https://www.facebook.com/sharer/sharer.php?u=${window.location.href}`;
    window.open(url, '_blank');
};

const copyLink = (videoData) => {
    navigator.clipboard.writeText(window.location.href);
    alert('링크가 복사되었습니다!');
    showShareMenu.value = false;
};
</script>

<style scoped>
.share-control {
    position: relative;
}

.share-dropdown {
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 0.5rem;
    background: rgba(0, 0, 0, 0.9);
    border-radius: 4px;
    overflow: hidden;
    min-width: 150px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.share-dropdown button {
    width: 100%;
    padding: 0.75rem 1rem;
    background: transparent;
    border: none;
    color: #fff;
    text-align: left;
    cursor: pointer;
    font-size: 0.875rem;
    transition: background 0.2s ease;
}

.share-dropdown button:hover {
    background: rgba(255, 255, 255, 0.1);
}
</style>
```

## 스타일 커스터마이징

플레이어는 CSS 변수를 통해 스타일을 커스터마이징할 수 있습니다:

```vue
<template>
    <div class="custom-player">
        <Player :video-id="123" />
    </div>
</template>

<style>
.custom-player {
    /* 프로그레스 바 색상 */
    --player-progress-color: #10b981;
    
    /* 컨트롤 버튼 호버 색상 */
    --player-control-hover: rgba(16, 185, 129, 0.2);
    
    /* 배경 그라디언트 */
    --player-gradient: linear-gradient(to top, rgba(0, 0, 0, 0.9), transparent);
}
</style>
```

## 이벤트

현재 Player 컴포넌트는 이벤트를 emit하지 않지만, 필요한 경우 videoElement ref를 통해 네이티브 비디오 이벤트를 직접 수신할 수 있습니다.

향후 버전에서 다음 이벤트들이 추가될 예정입니다:
- `@play`: 재생 시작
- `@pause`: 일시정지
- `@ended`: 재생 종료
- `@timeupdate`: 재생 시간 업데이트
- `@quality-change`: 화질 변경

## 문제 해결

### 비디오가 로드되지 않음
1. videoId가 올바른지 확인
2. API 엔드포인트(`/api/orbit-video-player/${id}`)가 정상 응답하는지 확인
3. 브라우저 콘솔에서 에러 메시지 확인

### 키보드 단축키가 작동하지 않음
- 플레이어 영역을 클릭하여 포커스를 주세요
- input, textarea 등의 입력 요소에서는 단축키가 비활성화됩니다

### HLS/DASH가 작동하지 않음
- `/vendor/cms-orbit/video/js/hls.js`와 `/vendor/cms-orbit/video/js/dashjs.js` 파일이 존재하는지 확인
- 네트워크 탭에서 스크립트가 로드되는지 확인

