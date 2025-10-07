# CMS-Orbit Video Package

[![Version](https://img.shields.io/badge/version-1.1.5-blue.svg)](https://github.com/your-org/cms-orbit-video)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

CMS-Orbit을 위한 포괄적인 비디오 관리 시스템입니다.

**주요 기능:** 다중 프로파일 인코딩, HLS/DASH 스트리밍, 시청 기록, 강의 모드

## 🎯 최신 업데이트 (v1.1.5)

### 시청 기록 및 강의 모드

```vue
<!-- 일반 비디오 재생 -->
<Player :video-id="123" />

<!-- 강의 모드 (빨리감기 제한) -->
<Player :video-id="123" :lecture-mode="true" />
```

- ✅ 시청 기록 자동 저장 (사용자별/세션별)
- ✅ 마지막 시청 위치 자동 복원
- ✅ 강의 모드: 빨리감기 제한으로 순차적 시청 강제
- ✅ 비회원 → 로그인 시 시청 기록 자동 연결

📖 [전체 변경사항 보기](docs/PATCH_NOTE_1.1.5.md)

## ✨ 주요 기능

| 기능 | 설명 |
|------|------|
| 🎥 **비디오 관리** | 다중 업로드, 메타데이터 자동 추출, 진행률 모니터링 |
| 🔄 **자동 인코딩** | 4K/FHD/HD/SD 다중 프로파일, HLS/DASH/Progressive MP4 |
| 🖼️ **미디어 생성** | 썸네일, 스프라이트 시트 자동 생성 |
| 📊 **시청 기록** | 사용자별/세션별 진행률 추적, 이어보기 |
| 🎓 **강의 모드** | 빨리감기 제한으로 순차적 학습 강제 |
| 🎛️ **관리자 UI** | Orchid Platform 통합, 실시간 로그 |
| 🔗 **VideoField** | Orchid 커스텀 필드로 간편한 비디오 연결 |

## 🚀 빠른 시작

```bash
# 1. 설치
composer require cms-orbit/video-field

# 2. 설정 발행
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="config"

# 3. 마이그레이션
php artisan migrate

# 4. 테마 스크립트 빌드
php artisan cms:build-theme-scripts
```

## ⚙️ 환경 설정

`.env` 파일 기본 설정:

```env
# FFmpeg (필수)
FFMPEG_BINARY_PATH=ffmpeg
FFPROBE_BINARY_PATH=ffprobe

# 저장소
VIDEO_STORAGE_DISK=public
VIDEO_MAX_FILE_SIZE=5368709120  # 5GB

# 시청 기록 (선택)
VIDEO_COMPLETION_THRESHOLD=0.9  # 90% 시청 시 완료
VIDEO_WATCH_HISTORY_INTERVAL=5  # 5초마다 저장
```

📖 [전체 설정 옵션 보기](config/orbit-video.php)

## 📖 기본 사용법

### 1. 모델에 트레이트 추가

```php
use CmsOrbit\VideoField\Traits\HasVideos;

class Post extends Model
{
    use HasVideos;
}
```

### 2. VideoField 사용 (Orchid)

```php
use App\Settings\Extends\OrbitLayout;
use CmsOrbit\VideoField\Fields\VideoField\VideoField;

public function layout(): iterable
{
    return [
        OrbitLayout::rows([
            VideoField::make('post.main_video')
                ->title('Main Video')
        ])
    ];
}
```

### 3. 프론트엔드에서 비디오 재생

```vue
<script setup>
import Player from '@orbit/video/Player.vue';

defineProps({
    videoId: Number,
    lectureMode: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <Player 
        :video-id="videoId" 
        :lecture-mode="lectureMode"
        show-title
        show-description
    />
</template>
```

## 📚 상세 문서

| 문서 | 설명 |
|------|------|
| [플레이어 사용 가이드](docs/PLAYER_USAGE.md) | Player 컴포넌트 props, 이벤트, 커스터마이징 |
| [플레이어 API](docs/PLAYER_API.md) | API 엔드포인트 및 응답 형식 |
| [설정 가이드](config/orbit-video.php) | 전체 설정 옵션 및 설명 |
| [패치 노트 v1.1.5](docs/PATCH_NOTE_1.1.5.md) | 시청 기록 및 강의 모드 상세 |

## 🎮 주요 Player Props

```vue
<Player
    :video-id="123"
    :lecture-mode="true"
    :autoplay="false"
    :muted="false"
    :show-title="true"
    :show-description="true"
    :show-quality-selector="true"
    :show-download="false"
    :use-native-controls="false"
    @play="handlePlay"
    @pause="handlePause"
    @ended="handleEnded"
/>
```

📖 [전체 Props 및 이벤트 보기](docs/PLAYER_USAGE.md)

## 🔧 관리자 패널

- **비디오 목록**: `/settings/orbit-video-fields/videos`
- **비디오 업로드**: `/settings/orbit-video-fields/videos/create`
- **비디오 편집**: `/settings/orbit-video-fields/videos/{id}/edit`
- **휴지통**: `/settings/orbit-video-fields/videos/trash`

## 🎯 시청 기록 API

```javascript
// 시청 진행률 조회
const response = await axios.get(`/api/orbit-video-player/${videoId}/watch-history`);
console.log(response.data.data.percent); // 시청 진행률
console.log(response.data.data.is_complete); // 완료 여부

// 시청 진행률 저장 (자동으로 호출됨)
await axios.post(`/api/orbit-video-player/${videoId}/progress`, {
    current_time: 30.5,
    duration: 120.0
});
```

## 🤝 기여

이슈 및 PR은 언제든지 환영합니다!

## 📄 라이선스

MIT License - 자유롭게 사용하세요.

## 👨‍💻 저자

- **xiso** - [ceo@amuz.co.kr](mailto:ceo@amuz.co.kr)

---

**CMS-Orbit** - 모던 Laravel CMS 시스템
