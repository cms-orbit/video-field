# CMS-Orbit Video Package

CMS-Orbit을 위한 포괄적인 비디오 업로드, 인코딩, 스트리밍 시스템입니다. 다중 프로파일 인코딩, 썸네일 생성, 스프라이트 이미지 생성, HLS/DASH 스트리밍을 지원합니다.

## 최신 업데이트 (v1.1.5)

### 🎯 시청 기록 및 강의 모드 기능 추가

**새로운 기능:**
- ✅ 비디오 시청 기록 저장 (사용자별/세션별)
- ✅ 시청 진행률 자동 추적 (5초 간격으로 저장)
- ✅ 마지막 시청 위치 자동 복원
- ✅ 강의 모드 (Lecture Mode) 지원 - 빨리감기 제한
- ✅ 시청 완료 여부 자동 판단 (설정 가능한 완료 기준)

**시청 기록 기능:**
- 비디오 총 길이 (duration)
- 시청 진행율 (percent)
- 현재 시청 지점 (seconds) - 마지막으로 보던 위치
- 최대 시청 시간 (played) - 빨리감기 제한 기준
- 시청 완료 여부 (is_complete)

**강의 모드:**
- `lectureMode` prop을 `true`로 설정하면 이전에 본 시점까지만 빨리감기 가능
- 온라인 강의나 교육 콘텐츠에서 순차적 시청을 강제할 때 유용

**설정 옵션 (config/orbit-video.php):**
```php
'player' => [
    'completion_threshold' => 0.9,        // 시청 완료 기준 (90%)
    'watch_history_interval' => 5,        // 시청 기록 저장 간격 (초)
    'lecture_mode_default' => false,      // 강의 모드 기본값
    'auto_save_progress' => true,         // 자동 저장 활성화
],
```

**사용 예시:**
```vue
<!-- 일반 모드 -->
<Player :video-id="123" />

<!-- 강의 모드 (빨리감기 제한) -->
<Player :video-id="123" :lecture-mode="true" />
```

## 주요 기능

### 🎥 비디오 관리
- **다중 파일 업로드**: Vue.js + Stimulus를 통한 직관적인 업로드 인터페이스
- **자동 메타데이터 추출**: FFprobe를 통한 비디오 해상도, 프레임레이트, 비트레이트 등 자동 분석
- **진행률 추적**: 실시간 업로드 및 인코딩 진행률 모니터링
- **에러 처리**: 상세한 에러 로그 및 재시도 메커니즘
- **UUID 기반**: 각 비디오 및 프로파일에 고유 UUID 할당

### 🔄 자동 인코딩
- **다중 프로파일 지원**: 4K, FHD, HD, SD 등 다양한 해상도로 자동 인코딩
- **적응형 비트레이트**: HLS 및 DASH 스트리밍을 위한 ABR 매니페스트 생성
- **유연한 인코딩 옵션**: Progressive MP4, HLS, DASH 출력 형식을 프로파일별로 선택 가능
- **백그라운드 처리**: Laravel Queue를 통한 비동기 인코딩 처리
- **진행률 모니터링**: 실시간 인코딩 상태 및 로그 확인

### 🖼️ 미디어 생성
- **썸네일 생성**: 자동 썸네일 추출 및 최적화 (설정 가능한 타임 포지션)
- **스프라이트 이미지**: 비디오 스크러빙을 위한 스프라이트 시트 생성
- **다양한 포맷**: JPEG, WebP 등 다양한 이미지 포맷 지원

### 🎛️ 관리자 인터페이스
- **Orchid Platform 통합**: CMS-Orbit의 관리자 패널과 완벽 통합
- **직관적인 UI**: Vue.js 기반 드래그 앤 드롭 업로드, 실시간 진행률 표시
- **상세한 로그**: FFmpeg 명령어, 에러 로그, 인코딩 상태 확인
- **프로파일 관리**: 커스텀 인코딩 프로파일 설정 및 관리
- **휴지통 기능**: 소프트 삭제 및 복원 지원

### 🔗 VideoField - Orchid 커스텀 필드
- **간편한 통합**: 관리자 화면에서 VideoField를 사용하여 비디오 연결
- **기존 비디오 선택**: 검색 및 선택 UI
- **직접 업로드**: 필드에서 바로 새 비디오 업로드
- **자동 저장**: 모델 저장 시 자동으로 관계 설정

## 설치

### 1. Composer를 통한 설치

```bash
composer require cms-orbit/video-field
```

### 2. 설정 파일 발행

```bash
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="config"
```

### 3. 마이그레이션 실행

```bash
php artisan migrate
```

### 4. 언어 파일 발행 (선택사항)

```bash
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="lang"
```

### 5. 테마 스크립트 빌드

VideoField의 Stimulus Controller를 등록하기 위해:

```bash
php artisan cms:build-theme-scripts
```

## 환경 설정

`.env` 파일에 다음 설정을 추가하세요:

```env
# 비디오 저장소 설정
VIDEO_STORAGE_DISK=public
VIDEO_STORAGE_PATH=videos/{videoId}
VIDEO_THUMBNAILS_PATH=videos/{videoId}/thumbnails
VIDEO_SPRITES_PATH=videos/{videoId}/sprites
VIDEO_PROFILES_PATH=videos/{videoId}/profiles
VIDEO_HLS_PATH=videos/{videoId}/hls
VIDEO_DASH_PATH=videos/{videoId}/dash

# FFmpeg 설정
FFMPEG_BINARY_PATH=ffmpeg
FFPROBE_BINARY_PATH=ffprobe
FFMPEG_TIMEOUT=3600
FFMPEG_THREADS=12

# 업로드 설정
VIDEO_MAX_FILE_SIZE=5368709120  # 5GB (기본값)

# 인코딩 형식 설정 (기본값)
VIDEO_EXPORT_PROGRESSIVE=true
VIDEO_EXPORT_HLS=true
VIDEO_EXPORT_DASH=true

# Queue 설정
VIDEO_QUEUE_NAME=default
```

## 사용법

### 1. 모델에 HasVideos 트레이트 추가

```php
<?php

namespace App\Settings\Entities\Post;

use App\Services\DynamicModel;
use CmsOrbit\VideoField\Traits\HasVideos;

class Post extends DynamicModel
{
    use HasVideos;
    
    /**
     * 비디오 필드 정의
     */
    protected array $videoFields = [
        'main_video',
        'trailer_video'
    ];
    
    /**
     * Global Scope를 통해 eager loading 되므로 
     * 필요하다면 videos 관계를 숨길 수 있습니다.
     */
    protected $hidden = [
        'videos'
    ];
}
```

**중요**: `HasVideos` 트레이트는 자동으로 다음을 수행합니다:
- Global Scope를 추가하여 `videos.profiles` 관계를 자동 eager loading
- 모델 조회 시 비디오 필드를 속성으로 자동 매핑
- 모델 저장 시 비디오 관계를 자동으로 저장

### 2. 관리자 화면에서 VideoField 사용

```php
<?php

namespace App\Settings\Entities\Post\Screens;

use App\Settings\Entities\Post\Post;
use CmsOrbit\VideoField\Fields\VideoField\VideoField;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PostEditScreen extends Screen
{
    public Post $post;

    public function query(Post $post): iterable
    {
        return [
            'post' => $post
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::rows([
                // 메인 비디오 필드
                VideoField::make('post.main_video')
                    ->title('Main Video')
                    ->help('Upload or select the main video for this post'),

                // 트레일러 비디오 필드 (업로드만 허용)
                VideoField::make('post.trailer_video')
                    ->title('Trailer Video')
                    ->withoutExists(), // 기존 비디오 선택 비활성화,

                // 기존 비디오만 선택 (업로드 비활성화)
                VideoField::make('post.related_video')
                    ->title('Related Video')
                    ->withoutUpload() // 업로드 비활성화
                    ->placeholder('Search for existing videos...')
                    ->maxResults(20),
            ])
        ];
    }
}
```

### 3. VideoField 메서드

```php
VideoField::make('field_name')
    ->withoutUpload()        // 업로드 기능 비활성화
    ->withoutExists()        // 기존 비디오 선택 비활성화
    ->placeholder('text')    // 검색 플레이스홀더
    ->maxResults(10)         // 최대 검색 결과 수
    ->size(5120)            // 최대 파일 크기 (MB)
    ->storage('public')     // 저장소 디스크
    ->path('videos/custom') // 업로드 경로
    ->group('video')        // Attachment 그룹
    ->errorSize('...')      // 크기 에러 메시지
    ->errorType('...')      // 타입 에러 메시지
```

### 4. 프론트엔드에서 비디오 데이터 사용

```php
// Post 조회
$post = Post::find(1);

// 비디오 데이터는 자동으로 속성에 매핑됨
$mainVideo = $post->main_video;

if ($mainVideo) {
    // 기본 정보
    $videoId = $mainVideo['id'];
    $title = $mainVideo['title'];
    $duration = $mainVideo['duration'];
    $status = $mainVideo['status'];
    
    // 썸네일 및 스프라이트
    $thumbnailUrl = $mainVideo['thumbnail_path'];
    $spriteUrl = $mainVideo['scrubbing_sprite_path'];
    
    // ABR 매니페스트
    $hlsUrl = $mainVideo['abr']['hls'];
    $dashUrl = $mainVideo['abr']['dash'];
    
    // 특정 프로파일 가져오기
    $fhdProfile = $mainVideo['profiles']['FHD@30fps'] ?? null;
    if ($fhdProfile) {
        $progressiveUrl = $fhdProfile['url'];
        $hlsUrl = $fhdProfile['url_hls'];
        $dashUrl = $fhdProfile['url_dash'];
        $fileSize = $fhdProfile['file_size'];
        $width = $fhdProfile['width'];
        $height = $fhdProfile['height'];
    }
    
    // 최고 품질 프로파일 (자동 선택)
    $bestProfile = $mainVideo['profiles']['best'];
    $bestUrl = $bestProfile['url'];
}
```

### 5. Video 모델 직접 사용

```php
use CmsOrbit\VideoField\Entities\Video\Video;

// 비디오 조회
$video = Video::find(1);

// 원본 파일 정보
$originalFile = $video->originalFile; // Attachment 관계
$originalName = $originalFile->original_name;

// 메타데이터
$duration = $video->duration;
$width = $video->original_width;
$height = $video->original_height;
$framerate = $video->original_framerate;
$bitrate = $video->original_bitrate;

// 상태
$status = $video->status; // pending, processing, completed, failed
$progress = $video->encoding_progress; // 0-100

// 프로파일
$profiles = $video->profiles;
foreach ($profiles as $profile) {
    $profileName = $profile->profile; // e.g., "FHD@30fps"
    $encoded = $profile->encoded;
    $status = $profile->status;
    $url = $profile->path;
}

// URL 헬퍼 메서드
$thumbnailUrl = $video->getThumbnailUrl();
$spriteUrl = $video->getSpriteUrl();
$hlsManifestUrl = $video->getHlsManifestUrl();
$dashManifestUrl = $video->getDashManifestUrl();
```

## 관리자 패널 기능

### 비디오 목록
`/settings/orbit-video-fields/videos`

- 모든 비디오 목록 조회
- 상태별 필터링 (pending, processing, completed, failed)
- 검색 기능
- 썸네일 미리보기
- 인코딩 진행률 표시

### 비디오 편집
`/settings/orbit-video-fields/videos/{id}/edit`

#### 기본 정보 탭
- 제목, 설명 수정
- 원본 파일 정보 확인
- 메타데이터 표시

#### 프로파일 및 인코딩 탭
- 활성화된 프로파일 목록
- 프로파일별 인코딩 상태
- FFmpeg 명령어 확인
- 에러 로그 확인
- 매니페스트 재생성
- 개별 프로파일 재인코딩
- 전체 재인코딩

#### 비디오 플레이어 탭
- Progressive MP4 플레이어
- HLS 플레이어
- DASH 플레이어
- 프로파일별 재생 테스트

### 비디오 업로드
`/settings/orbit-video-fields/videos/create`

- 드래그 앤 드롭 업로드
- 다중 파일 업로드
- 실시간 업로드 진행률
- 자동 인코딩 시작

### 휴지통
`/settings/orbit-video-fields/videos/trash`

- 삭제된 비디오 목록
- 복원 기능
- 영구 삭제

## 고급 설정

### 커스텀 인코딩 프로파일

`config/orbit-video.php`에서 기본 프로파일을 수정하거나 새로운 프로파일을 추가할 수 있습니다:

```php
'default_profiles' => [
    'Custom@60fps' => [
        'width' => 1920,
        'height' => 1080,
        'framerate' => 60,
        'bitrate' => '10M',
        'profile' => 'main',
        'level' => '4.1',
        'codec' => 'libx264',
    ],
],
```

### 인코딩 형식 설정

각 프로파일에서 어떤 형식으로 인코딩할지 설정할 수 있습니다:

```php
'default_encoding' => [
    'export_progressive' => true,  // MP4 프로그레시브 다운로드
    'export_hls' => true,         // HLS 스트리밍
    'export_dash' => true,        // DASH 스트리밍
],
```

### 썸네일 및 스프라이트 설정

```php
'thumbnails' => [
    'quality' => 100,
    'format' => 'jpeg',
    'time_position' => 5, // 비디오의 5초 지점에서 추출
],

'sprites' => [
    'enabled' => true,
    'width' => 160,
    'height' => 90,
    'columns' => 10,
    'rows' => 10,
    'interval' => 10, // 10초마다 프레임 추출
    'quality' => 70,
    'format' => 'jpeg',
],
```

## CLI 명령어

### 비디오 인코딩

```bash
# 비디오 인코딩 (모든 활성 프로파일)
php artisan video:encode {videoId}

# 특정 프로파일로 인코딩
php artisan video:encode {videoId} --profile="FHD@30fps"

# 강제 재인코딩 (이미 인코딩된 경우)
php artisan video:encode {videoId} --force

# 메모리 제한 조정
php -d memory_limit=2G artisan video:encode {videoId}
```

## Queue 작업

비디오 처리는 다음 Job들을 통해 백그라운드에서 실행됩니다:

- **VideoProcessJob**: 원본 비디오 메타데이터 추출 및 처리
- **VideoEncodeJob**: 프로파일별 인코딩 실행
- **VideoThumbnailJob**: 썸네일 생성
- **VideoSpriteJob**: 스프라이트 이미지 생성
- **VideoManifestJob**: HLS/DASH 매니페스트 생성

### Queue Worker 실행

```bash
# Queue 작업 실행
php artisan queue:work --queue=default

# 실패한 작업 확인
php artisan queue:failed

# 실패한 작업 재시도
php artisan queue:retry all

# 특정 작업 재시도
php artisan queue:retry {job-id}
```

## 데이터베이스 구조

### videos 테이블
- `id`: 비디오 고유 ID
- `uuid`: 고유 UUID
- `title`: 비디오 제목
- `description`: 비디오 설명
- `original_file_id`: 원본 파일 ID (Attachment)
- `duration`: 비디오 길이 (초)
- `original_width`: 원본 해상도 너비
- `original_height`: 원본 해상도 높이
- `original_framerate`: 원본 프레임레이트
- `original_bitrate`: 원본 비트레이트
- `original_size`: 원본 파일 크기
- `status`: 처리 상태 (pending, processing, completed, failed)
- `encoding_progress`: 인코딩 진행률 (0-100)
- `thumbnail_path`: 썸네일 경로
- `scrubbing_sprite_path`: 스프라이트 경로
- `hls_manifest_path`: HLS 매니페스트 경로
- `dash_manifest_path`: DASH 매니페스트 경로
- `deleted_at`: 소프트 삭제 시간
- `created_at`, `updated_at`: 생성/수정 시간

### video_profiles 테이블
- `id`: 프로파일 ID
- `uuid`: 고유 UUID
- `video_id`: 비디오 ID
- `profile`: 프로파일 이름 (e.g., "FHD@30fps")
- `encoded`: 인코딩 완료 여부
- `status`: 인코딩 상태
- `file_size`: 파일 크기
- `width`: 해상도 너비
- `height`: 해상도 높이
- `framerate`: 프레임레이트
- `bitrate`: 비트레이트
- `path`: Progressive MP4 경로
- `hls_path`: HLS 경로
- `dash_path`: DASH 경로
- `created_at`, `updated_at`: 생성/수정 시간

### video_encoding_logs 테이블
- `id`: 로그 ID
- `video_id`: 비디오 ID
- `profile_id`: 프로파일 ID
- `status`: 인코딩 상태
- `progress`: 진행률
- `error_message`: 에러 메시지
- `ffmpeg_command`: FFmpeg 명령어
- `started_at`: 시작 시간
- `completed_at`: 완료 시간

### video_field_relations 테이블 (Pivot)
- `id`: 관계 ID
- `video_id`: 비디오 ID
- `model_type`: 모델 타입 (Polymorphic)
- `model_id`: 모델 ID
- `field_name`: 필드 이름
- `sort_order`: 정렬 순서

## API 엔드포인트

### 비디오 정보 조회

```http
GET /api/videos/{uuid}
```

### 인코딩 상태 확인

```http
GET /api/videos/{uuid}/status
```

### 프로파일 정보 조회

```http
GET /api/videos/{uuid}/profiles
```

## 문제 해결

### FFmpeg 설치 확인

```bash
# FFmpeg 버전 확인
ffmpeg -version
ffprobe -version

# 설치 (Ubuntu/Debian)
sudo apt update
sudo apt install ffmpeg

# 설치 (macOS)
brew install ffmpeg

# 설치 (CentOS/RHEL)
sudo yum install epel-release
sudo yum install ffmpeg
```

### 권한 문제 해결

```bash
# 저장소 디렉토리 권한 설정
chmod -R 755 storage/app/public/videos
chown -R www-data:www-data storage/app/public/videos

# 심볼릭 링크 생성
php artisan storage:link
```

### Stimulus Controller 등록 확인

Stimulus Controller가 로드되지 않는 경우:

```bash
# 테마 스크립트 재빌드
php artisan cms:build-theme-scripts

# 프론트엔드 빌드
yarn build
```

### 메모리 부족 에러

대용량 비디오 처리 시 메모리 부족이 발생할 수 있습니다:

```bash
# PHP 메모리 제한 증가
php -d memory_limit=2G artisan video:encode {videoId}
```

`.env` 파일에서 FFmpeg 타임아웃 증가:

```env
FFMPEG_TIMEOUT=7200  # 2시간
```

## 성능 최적화

### 서버 요구사항
- **CPU**: 멀티코어 프로세서 (인코딩 성능에 중요)
- **RAM**: 최소 8GB (4K 비디오 처리 시 16GB 권장)
- **저장소**: SSD 권장 (빠른 I/O 성능)
- **네트워크**: 고속 인터넷 연결 (대용량 파일 업로드)

### FFmpeg 스레드 최적화

```php
// config/orbit-video.php
'ffmpeg' => [
    'threads' => 12, // CPU 코어 수에 맞게 조정
    'timeout' => 7200, // 2시간 (긴 비디오 처리용)
],
```

### Queue 병렬 처리

```bash
# 여러 worker를 동시에 실행
php artisan queue:work --queue=default &
php artisan queue:work --queue=default &
php artisan queue:work --queue=default &
```

## 아키텍처

### 비디오 처리 플로우

1. **업로드**: Vue.js 컴포넌트를 통해 비디오 업로드
2. **원본 저장**: Attachment로 원본 파일 저장
3. **메타데이터 추출**: FFprobe로 비디오 정보 분석
4. **프로파일 생성**: 설정된 프로파일에 따라 VideoProfile 레코드 생성
5. **인코딩 Job 디스패치**: 각 프로파일별로 VideoEncodeJob 큐에 추가
6. **인코딩 실행**: FFmpeg를 통해 프로파일별 인코딩
7. **썸네일/스프라이트 생성**: VideoThumbnailJob, VideoSpriteJob 실행
8. **매니페스트 생성**: VideoManifestJob으로 HLS/DASH 매니페스트 생성
9. **완료**: 상태 업데이트 및 사용 가능

### Model Events

- **Video 모델**: VideoObserver를 통해 인코딩 Job 자동 디스패치
- **HasVideos 트레이트**: 모델 이벤트를 통해 관계 자동 저장

## 라이선스

MIT License

## 기여하기

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 프론트엔드 비디오 플레이어

### Player Vue 컴포넌트

DASH/HLS 적응형 스트리밍을 지원하는 강력하고 커스터마이징 가능한 비디오 플레이어입니다.

#### 주요 기능

- 🎬 **커스텀 컨트롤**: 재생/일시정지, 프로그레스 바, 볼륨, 전체화면
- 🎨 **화질 선택**: Auto ABR 또는 수동 화질 선택 (1080p, 720p, 480p 등)
- 📥 **다운로드 기능**: Progressive 프로파일 다운로드
- 🖼️ **Sprite 프리뷰**: 프로그레스 바 호버 시 비디오 프레임 미리보기
- 📝 **제목/설명 오버레이**: 마우스 호버 시 좌상단에 비디오 정보 표시
- 🧩 **커스텀 액션 슬롯**: 공유, 좋아요 등 사용자 정의 버튼 추가
- 🎛️ **완전한 Props 제어**: autoplay, loop, muted 등 비디오 속성 제어
- 📱 **반응형 디자인**: 모바일/데스크톱 최적화
- 🔄 **자동 스트리밍 폴백**: DASH → HLS → Progressive 순서로 자동 시도
- 🚀 **동적 라이브러리 로딩**: HLS.js 및 Dash.js를 필요 시 자동 로드
- ⚡ **인코딩 상태 표시**: 처리 중인 비디오의 진행률 실시간 표시

#### 기본 사용법

```vue
<template>
    <div>
        <!-- 기본 플레이어 (네이티브 컨트롤) -->
        <Player :video="announcement.featured_video" />
        
        <!-- 비디오 ID만 전달 -->
        <Player :video-id="123" />
        
        <!-- 커스텀 컨트롤 + 화질 선택 + 다운로드 -->
        <Player 
            :video="video"
            :use-native-controls="false"
            :show-quality-selector="true"
            :show-download="true"
            :autoplay="false"
            :loop="false"
            class="w-full aspect-video rounded-lg"
        />
    </div>
</template>

<script setup>
import Player from '@orbit/video/Player.vue';
</script>
```

#### Props 옵션

**비디오 데이터**
- `video` (Object): 비디오 객체
- `videoId` (Number): 비디오 ID

**비디오 기본 속성**
- `autoplay` (Boolean, default: false): 자동 재생
- `loop` (Boolean, default: false): 반복 재생
- `muted` (Boolean, default: false): 음소거
- `playsinline` (Boolean, default: true): 인라인 재생 (iOS)
- `preload` (String, default: 'metadata'): 프리로드 옵션

**플레이어 UI 옵션**
- `useNativeControls` (Boolean, default: false): 네이티브 컨트롤 사용
- `showQualitySelector` (Boolean, default: true): 화질 선택 표시
- `showDownload` (Boolean, default: false): 다운로드 버튼 표시
- `showTitle` (Boolean, default: false): 제목 오버레이 표시 (마우스 오버 시)
- `showDescription` (Boolean, default: false): 설명 오버레이 표시 (마우스 오버 시)
- `debug` (Boolean, default: false): 디버그 모드

#### 커스텀 액션 슬롯

사용자 정의 버튼을 추가할 수 있습니다:

```vue
<Player :video="video" :use-native-controls="false">
    <template #actions="{ videoData, player, isPlaying }">
        <!-- 공유 버튼 -->
        <button @click="shareVideo(videoData)" class="control-button">
            <ShareIcon />
        </button>
        
        <!-- 좋아요 버튼 -->
        <button @click="likeVideo(videoData)" class="control-button">
            <HeartIcon />
        </button>
    </template>
</Player>
```

**슬롯 Props**
- `videoData`: 비디오 정보 객체
- `player`: 플레이어 인스턴스 (DASH 또는 HLS)
- `isPlaying`: 재생 중 여부

#### 사용 예시 (실전 코드)

다양한 실전 예시 코드를 제공합니다:

1. **[BasicPlayer.vue](docs/examples/BasicPlayer.vue)** - 가장 간단한 기본 플레이어
2. **[CustomControlsPlayer.vue](docs/examples/CustomControlsPlayer.vue)** - 커스텀 컨트롤 + 화질 선택 + 다운로드
3. **[AutoplayLoopPlayer.vue](docs/examples/AutoplayLoopPlayer.vue)** - 배경 비디오용 자동재생 + 반복
4. **[CustomActionsPlayer.vue](docs/examples/CustomActionsPlayer.vue)** - 공유/좋아요/재생목록 커스텀 버튼
5. **[TitleOverlayPlayer.vue](docs/examples/TitleOverlayPlayer.vue)** - 제목/설명 오버레이 표시

각 예시는 복사해서 바로 사용할 수 있습니다. 자세한 내용은 [예시 가이드](docs/examples/README.md)를 참고하세요.

#### 화질 선택 기능

ABR(Adaptive Bitrate) 스트리밍 환경에서 화질을 선택할 수 있습니다:

- **Auto**: 네트워크 상태에 따라 자동으로 최적 화질 선택
- **수동 선택**: 사용자가 원하는 화질 고정 (1080p, 720p, 480p 등)

```vue
<Player 
    :video="video"
    :show-quality-selector="true"
    :use-native-controls="false"
/>
```

#### 다운로드 기능

Progressive 인코딩된 프로파일이 있으면 다운로드 버튼이 활성화됩니다:

```vue
<Player 
    :video="video"
    :show-download="true"
    :use-native-controls="false"
/>
```

다운로드 메뉴에는 화질 레이블과 파일 크기가 표시됩니다.

#### 제목/설명 오버레이

비디오에 마우스를 올리면 좌상단에 제목과 설명이 표시됩니다:

```vue
<Player 
    :video="video"
    :show-title="true"
    :show-description="true"
    :use-native-controls="false"
/>
```

**특징**:
- 마우스 호버 시 부드러운 페이드 인/아웃 애니메이션
- 그라데이션 배경으로 가독성 향상
- 설명은 최대 3줄까지 표시 (초과 시 말줄임표)
- 각각 독립적으로 활성화/비활성화 가능

#### 주의사항

1. **자동재생 정책**: 대부분의 브라우저는 음소거되지 않은 자동재생을 차단합니다. 자동재생을 원한다면 `muted` 옵션을 함께 사용하세요.

   ```vue
   <Player :autoplay="true" :muted="true" />
   ```

2. **모바일 인라인 재생**: iOS에서 전체화면을 피하려면 `playsinline` 옵션을 true로 설정하세요.

3. **Progressive vs Adaptive**: Progressive는 단일 화질, Adaptive(DASH/HLS)는 다중 화질을 지원합니다.

4. **다운로드 권한**: 저작권이 있는 콘텐츠의 경우 `showDownload`를 false로 설정하세요.

#### 브라우저 지원

- **DASH**: Chrome, Firefox, Edge, Safari (dash.js 사용)
- **HLS**: Safari (네이티브), Chrome/Firefox/Edge (hls.js 사용)
- **Progressive**: 모든 브라우저

플레이어는 자동으로 브라우저에 맞는 최적의 스트리밍 방식을 선택합니다.

### Video Player API

프론트엔드 플레이어를 위한 전용 API를 제공합니다.

#### 주요 엔드포인트

```
GET  /api/orbit-video-player/{id}              # 비디오 정보 조회
POST /api/orbit-video-player/{id}/play         # 재생 시작 이벤트
POST /api/orbit-video-player/{id}/pause        # 일시정지 이벤트
POST /api/orbit-video-player/{id}/progress     # 재생 진행률 기록
POST /api/orbit-video-player/{id}/complete     # 재생 완료 이벤트
GET  /api/orbit-video-player/{id}/position     # 재생 위치 불러오기
POST /api/orbit-video-player/{id}/position     # 재생 위치 저장
POST /api/orbit-video-player/{id}/view         # 조회수 증가
POST /api/orbit-video-player/{id}/report-issue # 문제 리포트
GET  /api/orbit-video-player/{id}/analytics    # 분석 데이터 (관리자)
```

#### 구현 예정 기능

- 재생 이벤트 로깅
- 시청 시간 추적
- 시청 완료율 분석
- 재생 위치 저장/복원
- 조회수 관리
- 화질별 선택 통계
- 문제 리포트 시스템

자세한 API 문서는 [PLAYER_API.md](docs/PLAYER_API.md)를 참고하세요.

## 변경 로그

### v1.3.0 (2025-10-07)

#### Video Player 대폭 개선 🎉

**커스터마이징 가능한 Props 추가**
- 비디오 기본 속성: `autoplay`, `loop`, `muted`, `playsinline`, `preload`
- UI 컨트롤 옵션: `useNativeControls`, `showQualitySelector`, `showDownload`

**Sprite 이미지 프리뷰 기능**
- 프로그레스 바에 마우스를 올리면 해당 시간의 비디오 프레임 미리보기
- Sprite metadata를 활용한 효율적인 프레임 표시
- 시간 정보와 함께 표시되는 툴팁

**제목/설명 오버레이 기능**
- 비디오에 마우스 호버 시 좌상단에 제목과 설명 표시
- 부드러운 페이드 인/아웃 애니메이션
- Props로 독립적으로 활성화/비활성화 가능 (`showTitle`, `showDescription`)
- 그라데이션 배경과 텍스트 섀도우로 가독성 향상

**완전히 새로운 커스텀 컨트롤 UI**
- ▶️ 재생/일시정지 버튼
- ⏱️ 시간 표시 (현재/전체)
- 📊 프로그레스 바 (재생 + 버퍼링 진행도)
- 🔊 볼륨 컨트롤 (음소거 + 슬라이더)
- 🎨 화질 선택 드롭다운 (Auto + 수동 선택)
- 📥 다운로드 드롭다운 (Progressive 프로파일 목록)
- ⛶ 전체화면 버튼

**화질 선택 기능**
- Auto 모드: 네트워크 상태에 따라 자동 화질 조정
- 수동 선택: 사용자가 원하는 화질 고정 (1080p, 720p, 480p 등)
- DASH와 HLS 모두 지원

**다운로드 기능**
- Progressive 프로파일 목록 표시
- 화질별 파일 크기 표시 (선택사항)
- 의미있는 파일명으로 다운로드 (제목_화질.mp4)
- `format` 및 `status` 필드가 없는 프로파일도 지원 (유연한 필터링)

**커스텀 액션 슬롯**
- `#actions` 슬롯 추가로 사용자 정의 버튼 추가 가능
- 슬롯 Props: `videoData`, `player`, `isPlaying`
- 공유, 좋아요, 재생목록 등 자유롭게 확장

**반응형 디자인**
- 모바일 최적화: 작은 화면에서 볼륨 컨트롤 자동 숨김
- 터치 친화적인 버튼 크기
- 마우스 호버 시 컨트롤 자동 표시

**개선된 상태 관리**
- 플레이어 상태 추적: `isPlaying`, `currentTime`, `duration`, `volume`
- 비디오 이벤트 핸들링: timeupdate, play, pause, ended 등
- Computed 속성 활용으로 효율적인 렌더링

**문서 및 예시**
- 5가지 실전 예시 코드 제공 (BasicPlayer, CustomControlsPlayer, AutoplayLoopPlayer, CustomActionsPlayer, TitleOverlayPlayer)
- 각 예시는 복사해서 바로 사용 가능
- 예시 가이드 문서 포함

**하위 호환성**
- 기존 사용 방식 100% 유지
- 새로운 Props는 모두 선택사항
- 기존 코드 수정 없이 그대로 사용 가능

### v1.2.0
- **Video Player 컴포넌트**: 프론트엔드 비디오 플레이어 Vue 컴포넌트 추가
- **Video Player API**: 플레이어 전용 API 엔드포인트 분리 (VideoPlayerApiController)
- **스트리밍 폴백**: DASH → HLS → Progressive 자동 폴백 지원
- **동적 라이브러리 로딩**: HLS.js 및 Dash.js 동적 로드
- **확장 가능한 API**: 재생 이벤트, 분석, 조회수 등 확장 준비

### v1.1.2
- **VideoField 안정화**: 커스텀 필드의 저장 및 로딩 로직 개선
- **HasVideos 트레이트 최적화**: Global Scope를 통한 자동 eager loading
- **프로파일 매핑 개선**: 'best' 프로파일 자동 선택 기능

### v1.1.0
- **VideoField 추가**: Orchid 관리자 패널용 커스텀 필드
- **HasVideos 트레이트**: 모델에 비디오 기능 쉽게 추가
- **Global Scope**: 자동 eager loading으로 N+1 쿼리 방지
- **UUID 지원**: 각 비디오 및 프로파일에 UUID 추가

### v1.0.2
- **MP4 프로그레시브 다운로드 지원**: HLS/DASH가 지원되지 않는 환경을 위한 fallback
- **유연한 인코딩 옵션**: 프로파일별로 Progressive MP4, HLS, DASH 인코딩 형태 선택 가능
- **CDN 의존성 제거**: 모든 JavaScript 라이브러리를 로컬에 내장하여 오프라인 지원
- **자동 라이브러리 배포**: composer update/dump 시 자동으로 라이브러리 배포
- **DASH 스트림 개선**: 올바른 매니페스트 형식과 파일명 패턴으로 DASH 재생 안정성 향상
- **매니페스트 재생성 기능**: 관리자 패널에서 매니페스트 수동 재생성 가능
- **향상된 비디오 플레이어**: Progressive MP4, HLS, DASH 세 가지 형태의 테스트 플레이어 제공

### v1.0.1
- 버그 수정 및 성능 개선
- DASH 스트리밍 지원 추가
- ABR 매니페스트 생성 개선

### v1.0.0
- 초기 릴리스
- 비디오 업로드 및 인코딩 기능
- 다중 프로파일 지원
- 썸네일 및 스프라이트 생성
- HLS 스트리밍 지원
- Orchid Platform 통합

## 지원

문제가 발생하거나 질문이 있으시면 [Issues](https://github.com/cms-orbit/video-field/issues) 페이지에서 문의해주세요.
