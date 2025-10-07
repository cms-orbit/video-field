# Patch Note v1.1.5

**릴리즈 날짜:** 2025-01-07

## 🎯 주요 변경사항

### 1. 시청 기록 시스템 추가

비디오 시청 기록을 자동으로 추적하고 저장하는 기능이 추가되었습니다.

#### 새로운 데이터베이스 테이블
- `video_watch_histories` 테이블 생성
  - `video_id`: 비디오 ID (FK)
  - `user_id`: 사용자 ID (nullable, FK)
  - `session_id`: 세션 ID (nullable, 비로그인 사용자용)
  - `duration`: 비디오 총 길이 (초)
  - `percent`: 시청 진행율 (0-100)
  - `seconds`: 현재 시청 지점 (초)
  - `played`: 마지막 시청 시간 (초) - 빨리감기 제한 기준
  - `is_complete`: 시청 완료 여부 (boolean)

#### 새로운 모델
- `VideoWatchHistory` 모델 추가
  - `updateProgress()`: 시청 진행률 업데이트
  - `canSeekTo()`: 특정 시간으로 탐색 가능 여부 확인 (강의 모드용)
  - `getMaxSeekableTime()`: 최대 탐색 가능 시간 반환

### 2. 강의 모드 (Lecture Mode)

온라인 강의나 교육 콘텐츠에서 순차적 시청을 강제하는 기능이 추가되었습니다.

#### 작동 방식
- `lectureMode` prop이 `true`일 때 활성화
- 사용자가 이전에 본 시점(`played`)까지만 빨리감기 가능
- 시청하지 않은 부분으로 탐색 시도 시 자동으로 최대 시청 지점으로 되돌림

#### 사용 예시
```vue
<Player 
    :video-id="123" 
    :lecture-mode="true"
/>
```

### 3. 시청 기록 자동 저장

비디오 재생 중 자동으로 시청 기록을 저장합니다.

#### 기능
- 5초 간격으로 자동 저장 (설정 변경 가능)
- 재생 중일 때만 저장
- 컴포넌트 unmount 시 최종 저장
- 로그인 사용자는 `user_id`로, 비로그인 사용자는 `session_id`로 구분

#### 저장 로직
```javascript
// 예시: 1분짜리 비디오를 30초까지 보다가 15초로 되돌린 경우
{
    duration: 60.0,      // 총 길이
    seconds: 15.0,       // 현재 위치
    played: 30.0,        // 최대 시청 시간
    percent: 50.0,       // 진행율 (played 기준)
    is_complete: false   // 완료 여부
}
```

### 4. 시청 완료 판단

설정 가능한 임계값에 따라 시청 완료 여부를 자동 판단합니다.

#### 설정 (config/orbit-video.php)
```php
'player' => [
    'completion_threshold' => 0.9,  // 90% 이상 시청 시 완료로 간주
],
```

### 5. 마지막 시청 위치 복원

비디오 로드 시 이전에 보던 위치를 자동으로 복원합니다.

#### 동작
- 비디오 메타데이터 로드 완료 후 자동 복원
- `watch_history.seconds` 값으로 재생 위치 설정
- 디버그 모드에서 복원 로그 출력

## 🔧 API 변경사항

### 새로운 API 엔드포인트

#### 1. GET `/api/orbit-video-player/{id}/watch-history`
시청 기록 조회

**Response:**
```json
{
    "success": true,
    "data": {
        "seconds": 30.5,
        "played": 45.2,
        "percent": 45.2,
        "is_complete": false,
        "max_seekable_time": 45.2
    }
}
```

#### 2. POST `/api/orbit-video-player/{id}/progress`
시청 진행률 저장

**Request:**
```json
{
    "current_time": 30.5,
    "duration": 120.0
}
```

**Response:**
```json
{
    "success": true,
    "message": "Progress recorded",
    "data": {
        "seconds": 30.5,
        "played": 45.2,
        "percent": 37.7,
        "is_complete": false
    }
}
```

### 수정된 API 엔드포인트

#### GET `/api/orbit-video-player/{id}`
비디오 정보에 시청 기록 포함

**Response에 추가된 필드:**
```json
{
    "id": 1,
    "title": "Sample Video",
    // ... 기존 필드들 ...
    "watch_history": {
        "seconds": 30.5,
        "played": 45.2,
        "percent": 37.7,
        "is_complete": false
    }
}
```

## 📝 설정 변경사항

### config/orbit-video.php

새로운 `player` 섹션이 추가되었습니다:

```php
'player' => [
    // 시청 완료 기준 (0.0 ~ 1.0, 예: 0.9 = 90%)
    'completion_threshold' => env('VIDEO_COMPLETION_THRESHOLD', 0.9),

    // 시청 기록 저장 간격 (초)
    'watch_history_interval' => env('VIDEO_WATCH_HISTORY_INTERVAL', 5),

    // 강의 모드 기본값 (true면 빨리감기 제한)
    'lecture_mode_default' => env('VIDEO_LECTURE_MODE_DEFAULT', false),

    // 시청 기록 자동 저장 활성화
    'auto_save_progress' => env('VIDEO_AUTO_SAVE_PROGRESS', true),
],
```

### 환경 변수 (.env)

```env
# 시청 완료 기준 (0.9 = 90%)
VIDEO_COMPLETION_THRESHOLD=0.9

# 시청 기록 저장 간격 (초)
VIDEO_WATCH_HISTORY_INTERVAL=5

# 강의 모드 기본값
VIDEO_LECTURE_MODE_DEFAULT=false

# 시청 기록 자동 저장
VIDEO_AUTO_SAVE_PROGRESS=true
```

## 🎨 Player.vue 변경사항

### 새로운 Props

#### `lectureMode`
- **Type:** Boolean
- **Default:** `false`
- **Description:** 강의 모드 활성화. `true`일 때 빨리감기 제한

```vue
<Player 
    :video-id="123" 
    :lecture-mode="true"
/>
```

### 새로운 내부 상태

```javascript
// 시청 기록 상태
const watchHistory = ref(null);
const maxSeekableTime = ref(0);
let watchHistoryInterval = null;
```

### 새로운 메서드

#### `startWatchHistoryTracking()`
시청 기록 자동 저장 시작

#### `stopWatchHistoryTracking()`
시청 기록 자동 저장 중지

#### `saveWatchProgress()`
현재 시청 진행률을 서버에 저장

### 수정된 메서드

#### `handleTimeUpdate()`
- 강의 모드일 때 빨리감기 제한 로직 추가
- `maxSeekableTime`을 초과하면 자동으로 되돌림

#### `handleLoadedMetadata()`
- 시청 기록에서 마지막 재생 위치 복원
- 시청 기록 자동 저장 시작

#### `seek()`, `seekBySeconds()`
- 강의 모드일 때 `maxSeekableTime` 제한 적용

#### `onBeforeUnmount()`
- 컴포넌트 unmount 시 시청 기록 최종 저장
- 시청 기록 추적 중지

## 🔄 마이그레이션

### 실행 방법

```bash
php artisan migrate
```

### 마이그레이션 파일
- `2024_12_26_000005_create_video_watch_histories_table.php`

### 롤백

```bash
php artisan migrate:rollback --step=1
```

## 🚀 업그레이드 가이드

### 1단계: 패키지 업데이트

```bash
composer update cms-orbit/video-field
```

### 2단계: 마이그레이션 실행

```bash
php artisan migrate
```

### 3단계: 설정 파일 업데이트 (선택사항)

```bash
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="config" --force
```

### 4단계: 테마 스크립트 재빌드

```bash
php artisan cms:build-theme-scripts
```

### 5단계: 환경 변수 추가 (선택사항)

`.env` 파일에 새로운 설정 추가:

```env
VIDEO_COMPLETION_THRESHOLD=0.9
VIDEO_WATCH_HISTORY_INTERVAL=5
VIDEO_LECTURE_MODE_DEFAULT=false
VIDEO_AUTO_SAVE_PROGRESS=true
```

## 📊 사용 사례

### 일반 비디오 플레이어

```vue
<template>
    <Player :video-id="videoId" />
</template>

<script setup>
import Player from '@orbit/video/Player.vue';

const videoId = 123;
</script>
```

### 강의 모드 플레이어

```vue
<template>
    <Player 
        :video-id="lectureVideo.id" 
        :lecture-mode="true"
        :show-title="true"
    />
</template>

<script setup>
import Player from '@orbit/video/Player.vue';

const lectureVideo = {
    id: 456,
    title: 'Introduction to Laravel'
};
</script>
```

### 사용자 시청 기록 확인

```javascript
// API를 통한 시청 기록 조회
const response = await axios.get(`/api/orbit-video-player/${videoId}/watch-history`);
console.log('시청 진행률:', response.data.data.percent + '%');
console.log('시청 완료 여부:', response.data.data.is_complete);
```

## 🐛 버그 수정

- 없음 (새로운 기능 추가)

## ⚠️ 주의사항

### Breaking Changes
- 없음 (하위 호환성 유지)

### 권장사항
1. 시청 기록 기능을 사용하려면 마이그레이션을 실행해야 합니다.
2. 강의 모드는 기본적으로 비활성화되어 있으므로 필요한 경우에만 활성화하세요.
3. 시청 기록은 세션 기반이므로 비로그인 사용자의 경우 브라우저를 닫으면 기록이 유지되지 않을 수 있습니다.

## 📚 관련 문서

- [플레이어 사용 가이드](./PLAYER_USAGE.md)
- [VideoField 사용 가이드](./VIDEO_FIELD.md)
- [API 레퍼런스](./API_REFERENCE.md)

## 🙏 기여자

- xiso (ceo@amuz.co.kr)

---

**전체 변경 이력은 [GitHub Releases](https://github.com/your-org/cms-orbit-video/releases)에서 확인하세요.**

