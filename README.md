# CMS-Orbit Video Package

CMS-Orbit을 위한 포괄적인 비디오 업로드, 인코딩, 스트리밍 시스템입니다. 다중 프로파일 인코딩, 썸네일 생성, 스프라이트 이미지 생성, HLS 스트리밍을 지원합니다.

## 주요 기능

### 🎥 비디오 관리
- **다중 파일 업로드**: 여러 비디오 파일을 동시에 업로드
- **자동 메타데이터 추출**: 비디오 해상도, 프레임레이트, 비트레이트 등 자동 분석
- **진행률 추적**: 실시간 업로드 및 인코딩 진행률 모니터링
- **에러 처리**: 상세한 에러 로그 및 재시도 메커니즘

### 🔄 자동 인코딩
- **다중 프로파일 지원**: 4K, FHD, HD, SD 등 다양한 해상도로 자동 인코딩
- **적응형 비트레이트**: HLS 스트리밍을 위한 ABR 매니페스트 생성
- **백그라운드 처리**: Queue를 통한 비동기 인코딩 처리
- **진행률 모니터링**: 실시간 인코딩 상태 및 로그 확인

### 🖼️ 미디어 생성
- **썸네일 생성**: 자동 썸네일 추출 및 최적화
- **스프라이트 이미지**: 비디오 스크러빙을 위한 스프라이트 시트 생성
- **다양한 포맷**: JPEG, WebP 등 다양한 이미지 포맷 지원

### 🎛️ 관리자 인터페이스
- **Orchid Platform 통합**: CMS-Orbit의 관리자 패널과 완벽 통합
- **직관적인 UI**: 드래그 앤 드롭 업로드, 실시간 진행률 표시
- **상세한 로그**: FFmpeg 명령어, 에러 로그, 인코딩 상태 확인
- **프로파일 관리**: 커스텀 인코딩 프로파일 설정

## 설치

### Composer를 통한 설치

```bash
composer require cms-orbit/video-field
```

### 서비스 프로바이더 등록

패키지는 자동으로 등록되지만, 수동으로 등록하려면 `config/app.php`에 추가하세요:

```php
'providers' => [
    // ...
    CmsOrbit\VideoField\VideoServiceProvider::class,
],
```

### 설정 파일 발행

```bash
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="config"
```

### 마이그레이션 실행

```bash
php artisan migrate
```

### 언어 파일 발행

```bash
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="lang"
```

## 환경 설정

`.env` 파일에 다음 설정을 추가하세요:

```env
# 비디오 저장소 설정
VIDEO_STORAGE_DISK=public
VIDEO_STORAGE_PATH=videos/{videoId}
VIDEO_THUMBNAILS_PATH=videos/{videoId}/thumbnails
VIDEO_SPRITES_PATH=videos/{videoId}/sprites

# FFmpeg 설정
FFMPEG_BINARY_PATH=ffmpeg
FFPROBE_BINARY_PATH=ffprobe
FFMPEG_TIMEOUT=3600
FFMPEG_THREADS=12

# 업로드 설정
VIDEO_MAX_FILE_SIZE=5368709120  # 5GB
VIDEO_CHUNK_SIZE=1048576        # 1MB

# Queue 설정
VIDEO_QUEUE_NAME=default
VIDEO_MAX_TRIES=3
VIDEO_RETRY_DELAY=300

# 정리 설정
VIDEO_AUTO_CLEANUP=true
VIDEO_TEMP_FILE_TTL=86400
VIDEO_FAILED_JOB_TTL=604800
```

## 사용법

### 1. 모델에 HasVideos 트레이트 추가

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use CmsOrbit\VideoField\Traits\HasVideos;

class Post extends Model
{
    use HasVideos;
    
    // 비디오와의 관계는 자동으로 설정됩니다
}
```

### 2. 관리자 화면에서 비디오 관리

비디오 패키지는 자동으로 Orchid Platform에 통합되어 다음 화면들을 제공합니다:

- **비디오 목록**: 모든 비디오 목록 및 상태 확인
- **비디오 업로드**: 드래그 앤 드롭으로 비디오 업로드
- **비디오 편집**: 메타데이터 수정 및 인코딩 설정
- **인코딩 모니터링**: 실시간 인코딩 진행률 및 로그 확인

### 3. 프론트엔드에서 비디오 사용

```php
// 단일 비디오 가져오기
$video = Video::find(1);

// 비디오 URL 가져오기
$videoUrl = $video->getVideoUrl('FHD@30fps');
$thumbnailUrl = $video->getThumbnailUrl();
$spriteUrl = $video->getSpriteUrl();

// HLS 스트리밍 URL
$hlsUrl = $video->getHlsUrl();
```

### 4. 관계형 모델에서 비디오 사용

```php
// Post 모델에서 비디오 가져오기
$post = Post::find(1);
$videos = $post->videos; // 모든 비디오
$mainVideo = $post->videos()->where('is_main', true)->first();

// 비디오 추가
$post->videos()->attach($videoId, ['is_main' => true]);
```

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

### 모델별 프로파일 설정

특정 모델에서만 사용할 프로파일을 설정할 수 있습니다:

```php
class Post extends Model
{
    use HasVideos;
    
    protected $videoProfiles = [
        'FHD@30fps' => [
            'width' => 1920,
            'height' => 1080,
            'framerate' => 30,
            'bitrate' => '8M',
            'profile' => 'main',
            'level' => '4.0',
            'codec' => 'libx264',
        ],
    ];
}
```

### Queue 작업 모니터링

```bash
# 인코딩 작업 실행
php artisan video:encode {videoId}

# 특정 프로파일로 인코딩
php artisan video:encode {videoId} --profile="FHD@30fps"

# 강제 재인코딩
php artisan video:encode {videoId} --force
```

## API 엔드포인트

### 비디오 업로드

```http
POST /api/videos/upload
Content-Type: multipart/form-data

{
    "file": "video.mp4",
    "title": "My Video",
    "description": "Video description"
}
```

### 비디오 정보 조회

```http
GET /api/videos/{id}
```

### 인코딩 상태 확인

```http
GET /api/videos/{id}/encoding-status
```

## 데이터베이스 구조

### videos 테이블
- `id`: 비디오 고유 ID
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
- `created_at`, `updated_at`: 생성/수정 시간

### video_profiles 테이블
- `id`: 프로파일 ID
- `name`: 프로파일 이름
- `width`: 해상도 너비
- `height`: 해상도 높이
- `framerate`: 프레임레이트
- `bitrate`: 비트레이트
- `profile`: H.264 프로파일
- `level`: H.264 레벨
- `codec`: 코덱
- `is_active`: 활성화 여부

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
```

### Queue 작업 확인

```bash
# Queue 상태 확인
php artisan queue:work --queue=default

# 실패한 작업 확인
php artisan queue:failed

# 실패한 작업 재시도
php artisan queue:retry all
```

## 성능 최적화

### 서버 요구사항
- **CPU**: 멀티코어 프로세서 (인코딩 성능에 중요)
- **RAM**: 최소 8GB (4K 비디오 처리 시 16GB 권장)
- **저장소**: SSD 권장 (빠른 I/O 성능)
- **네트워크**: 고속 인터넷 연결 (대용량 파일 업로드)

### 설정 최적화

```php
// config/orbit-video.php
'ffmpeg' => [
    'threads' => 12, // CPU 코어 수에 맞게 조정
    'timeout' => 7200, // 2시간 (긴 비디오 처리용)
],
```

## 라이선스

MIT License

## 기여하기

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 지원

문제가 발생하거나 질문이 있으시면 [Issues](https://github.com/cms-orbit/video-field/issues) 페이지에서 문의해주세요.

## 변경 로그

### v1.0.2 (2024-09-14)
- **MP4 프로그레시브 다운로드 지원**: HLS/DASH가 지원되지 않는 환경을 위한 fallback
- **유연한 인코딩 옵션**: 프로필별로 Progressive MP4, HLS, DASH 인코딩 형태 선택 가능
- **CDN 의존성 제거**: 모든 JavaScript 라이브러리를 로컬에 내장하여 오프라인 지원
- **자동 라이브러리 배포**: composer update/dump 시 자동으로 라이브러리 배포
- **DASH 스트림 개선**: 올바른 매니페스트 형식과 파일명 패턴으로 DASH 재생 안정성 향상
- **SourceBuffer 에러 해결**: DASH.js에서 발생하는 SourceBuffer 관련 콘솔 에러 필터링
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