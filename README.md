# CMS-Orbit Video Field Package

[![Tests](https://github.com/cms-orbit/video-field/actions/workflows/tests.yml/badge.svg)](https://github.com/cms-orbit/video-field/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/cms-orbit/video-field.svg)](https://packagist.org/packages/cms-orbit/video-field)
[![Total Downloads on Packagist](https://img.shields.io/packagist/dt/cms-orbit/video-field.svg)](https://packagist.org/packages/cms-orbit/video-field)

CMS-Orbit 엔티티를 위한 고급 비디오 필드 시스템입니다. 다중 프로파일 인코딩, ABR 스트리밍, Orchid 관리자 패널 통합을 지원합니다.

## 🚀 주요 기능

- **다중 프로파일 인코딩**: 240p ~ 4K 해상도 지원
- **ABR 스트리밍**: HLS/DASH 매니페스트 자동 생성
- **Orchid 통합**: 관리자 패널 필드 지원
- **비동기 처리**: Queue 기반 Job 시스템
- **프로파일 폴백**: 상위 해상도 요청 시 하위 해상도 제공
- **썸네일 생성**: 자동 썸네일 및 스프라이트 생성
- **Trait 기반**: HasVideoField trait로 간편한 사용

## 📦 설치

### 1. Composer로 설치

```bash
composer require cms-orbit/video-field
```

### 2. 서비스 프로바이더 등록

`config/app.php`에 서비스 프로바이더를 추가합니다:

```php
'providers' => [
    // ...
    CmsOrbit\VideoField\VideoServiceProvider::class,
],
```

### 3. 마이그레이션 실행

```bash
php artisan migrate
```

### 4. 설정 파일 발행 (선택사항)

```bash
php artisan vendor:publish --tag=video-field-config
```

## 🔧 설정

### 기본 설정

`config/video.php` 파일에서 다음 설정을 조정할 수 있습니다:

```php
return [
    'storage' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'video_path' => env('VIDEO_STORAGE_PATH', 'videos/{videoId}'),
        'thumbnails_path' => env('VIDEO_THUMBNAILS_PATH', 'videos/{videoId}/thumbnails'),
        'sprites_path' => env('VIDEO_SPRITES_PATH', 'videos/{videoId}/sprites'),
    ],
    
    'ffmpeg' => [
        'binary_path' => env('FFMPEG_BINARY_PATH', 'ffmpeg'),
        'ffprobe_path' => env('FFPROBE_BINARY_PATH', 'ffprobe'),
        'timeout' => env('FFMPEG_TIMEOUT', 3600),
    ],
    
    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'redis'),
        'queue_name' => env('VIDEO_QUEUE_NAME', 'encode_video'),
        'max_tries' => env('VIDEO_MAX_TRIES', 3),
        'retry_delay' => env('VIDEO_RETRY_DELAY', 300),
    ],
];
```

### 환경 변수

`.env` 파일에 다음 변수들을 설정하세요:

```env
# Storage
MEDIA_DISK=public
VIDEO_STORAGE_PATH=videos/{videoId}
VIDEO_THUMBNAILS_PATH=videos/{videoId}/thumbnails
VIDEO_SPRITES_PATH=videos/{videoId}/sprites

# FFmpeg
FFMPEG_BINARY_PATH=/usr/bin/ffmpeg
FFPROBE_BINARY_PATH=/usr/bin/ffprobe
FFMPEG_TIMEOUT=3600

# Queue
QUEUE_CONNECTION=redis
VIDEO_QUEUE_NAME=encode_video
VIDEO_MAX_TRIES=3
VIDEO_RETRY_DELAY=300

# Upload
VIDEO_MAX_FILE_SIZE=5368709120
VIDEO_CHUNK_SIZE=1048576
```

## 📖 사용법

### 1. 모델에 HasVideoField Trait 추가

```php
<?php

namespace App\Models;

use CmsOrbit\VideoField\Traits\HasVideoField;
use CmsOrbit\VideoField\Entities\Video\Video;

class Announcement extends DynamicModel
{
    use HasVideoField;
    
    protected $videoFields = [
        'featured_video' => [
            'profiles' => ['HD@30fps', 'SD@30fps'],
            'auto_thumbnail' => true,
        ],
        'promo_video' => [
            'profiles' => ['FHD@30fps', 'HD@30fps'],
            'auto_thumbnail' => true,
        ],
    ];
    
    // 선택사항: 커스텀 프로파일 정의
    protected function getVideoProfiles(): array
    {
        return [
            'FHD@30fps' => [
                'width' => 1920, 
                'height' => 1080, 
                'framerate' => 30, 
                'bitrate' => '8M'
            ],
            'HD@30fps' => [
                'width' => 1280, 
                'height' => 720, 
                'framerate' => 30, 
                'bitrate' => '4M'
            ],
        ];
    }
}
```

### 2. Orchid Screen에서 VideoUpload 필드 사용

```php
<?php

namespace App\Orchid\Screens;

use Orchid\Screen\Screen;
use Orchid\Screen\Fields\Layout;
use CmsOrbit\VideoField\Fields\VideoUpload\VideoUpload;

class AnnouncementEditScreen extends Screen
{
    public function layout(): array
    {
        return [
            Layout::rows([
                VideoUpload::make('featured_video')
                    ->title('Featured Video')
                    ->maxFileSize(2048) // 2GB
                    ->chunkSize(1) // 1MB chunks
                    ->autoProcess(true)
                    ->showProgress(true)
                    ->multiple(false)
                    ->allowedExtensions(['mp4', 'mov', 'avi'])
                    ->help('Upload a featured video for this announcement'),
                    
                VideoUpload::make('promo_video')
                    ->title('Promotional Video')
                    ->maxFileSize(1024) // 1GB
                    ->autoProcess(true)
                    ->help('Upload a promotional video'),
            ]),
        ];
    }
}
```

### 3. 비디오 데이터 접근

```php
$announcement = Announcement::find(1);

// 비디오 객체 가져오기
$video = $announcement->getVideo('featured_video');

// 비디오 URL 가져오기 (프로파일 폴백 포함)
$videoUrl = $video->getUrl('HD@30fps'); // HD 프로파일이 없으면 SD로 폴백

// ABR 스트리밍 URL
$hlsUrl = $video->getHlsManifestUrl();
$dashUrl = $video->getDashManifestUrl();

// 플레이어 메타데이터
$playerData = $video->getPlayerMetadata();
```

### 4. 프론트엔드에서 비디오 플레이어 사용

```vue
<template>
  <div>
    <VideoPlayer 
      :video="videoData"
      :autoplay="false"
      :controls="true"
      :width="640"
      :height="360"
    />
  </div>
</template>

<script setup>
import VideoPlayer from '@/packages/cms-orbit-video/resources/js/Components/VideoPlayer.vue'

const props = defineProps({
  videoData: {
    type: Object,
    required: true
  }
})
</script>
```

## 🎯 기본 프로파일

패키지는 다음 기본 프로파일을 제공합니다:

| 프로파일 | 해상도 | 프레임레이트 | 비트레이트 | 용도 |
|---------|--------|-------------|-----------|------|
| 4K@60fps | 3840x2160 | 60fps | 15M | 고화질 콘텐츠 |
| 4K@30fps | 3840x2160 | 30fps | 10M | 4K 콘텐츠 |
| FHD@60fps | 1920x1080 | 60fps | 12M | 고프레임레이트 |
| FHD@30fps | 1920x1080 | 30fps | 8M | 풀HD 콘텐츠 |
| HD@30fps | 1280x720 | 30fps | 4M | HD 콘텐츠 |
| SD@30fps | 640x480 | 30fps | 2M | 모바일 최적화 |

## 🔄 비동기 처리

### Job 체인

비디오 업로드 시 다음 Job들이 순차적으로 실행됩니다:

1. **VideoEncodeJob**: 다중 프로파일 인코딩
2. **VideoThumbnailJob**: 썸네일 생성
3. **VideoSpriteJob**: 스프라이트 시트 생성
4. **VideoManifestJob**: ABR 매니페스트 생성

### Queue 설정

```bash
# Queue 워커 시작
php artisan queue:work --queue=encode_video

# 또는 Supervisor 사용
[program:video-encode]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=encode_video --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

## 🧪 테스트

### 테스트 실행

```bash
# 모든 테스트 실행
php artisan test packages/cms-orbit-video/tests/

# 특정 테스트 실행
php artisan test packages/cms-orbit-video/tests/Unit/VideoModelTest.php
```

### 테스트 결과

- **VideoModelTest**: 8/8 통과 ✅
- **AbrManifestServiceTest**: 6/6 통과 ✅  
- **VideoJobsTest**: 11/11 통과 ✅
- **VideoIntegrationTest**: 5/5 통과 ✅

**총 30개 테스트 모두 통과!** 🎉

## 📚 API 참조

### Video 모델

```php
// 비디오 URL 가져오기 (프로파일 폴백 포함)
$video->getUrl(?string $profile = null): ?string

// ABR 매니페스트 URL
$video->getHlsManifestUrl(): ?string
$video->getDashManifestUrl(): ?string

// 플레이어 메타데이터
$video->getPlayerMetadata(): array

// 사용 가능한 프로파일
$video->getAvailableProfiles(): array

// ABR 지원 여부
$video->supportsAbr(): bool
```

### HasVideoField Trait

```php
// 비디오 객체 가져오기
$model->getVideo(string $field): ?Video

// 비디오 URL 가져오기
$model->getVideoUrl(string $field, ?string $profile = null): ?string

// 비디오 존재 여부
$model->hasVideo(string $field): bool

// 비디오 첨부
$model->attachVideo(string $field, Video $video): void
```

## 🛠️ 명령어

### 콘솔 명령어

```bash
# 비디오 인코딩
php artisan video:encode {video_id} [--profile=] [--force]

# 썸네일 생성
php artisan video:thumbnail {video_id} [--time=5] [--force]

# 스프라이트 생성
php artisan video:sprite {video_id} [--frames=100] [--columns=10] [--rows=10] [--force]

# 전체 프로세스
php artisan video:process-all {video_id} [--force]
```

## 🔧 문제 해결

### 일반적인 문제들

1. **FFmpeg not found**
   ```bash
   # FFmpeg 설치
   sudo apt-get install ffmpeg
   
   # 경로 확인
   which ffmpeg
   which ffprobe
   ```

2. **Queue Job 실패**
   ```bash
   # Queue 상태 확인
   php artisan queue:failed
   
   # 실패한 Job 재시도
   php artisan queue:retry all
   ```

3. **권한 문제**
   ```bash
   # Storage 디렉토리 권한 설정
   chmod -R 775 storage/app/public/videos
   chown -R www-data:www-data storage/app/public/videos
   ```

## 🤝 기여하기

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 라이선스

이 프로젝트는 MIT 라이선스 하에 배포됩니다. 자세한 내용은 [LICENSE](LICENSE) 파일을 참조하세요.

## 🆘 지원

- [Issues](https://github.com/cms-orbit/video-field/issues)
- [Documentation](https://github.com/cms-orbit/video-field/wiki)
- [Discussions](https://github.com/cms-orbit/video-field/discussions)

---

**CMS-Orbit Video Field Package** - 고급 비디오 필드 시스템으로 CMS-Orbit을 더욱 강력하게 만들어보세요! 🎬
