# CMS-Orbit Video Package

Laravel 기반의 강력한 비디오 처리 패키지입니다. FFmpeg를 활용한 다중 프로파일 인코딩, 썸네일 생성, 스프라이트 시트 생성 등 완전한 비디오 관리 시스템을 제공합니다.

## ✨ 주요 기능

- **🎬 다중 프로파일 인코딩**: 4K, FHD, HD 등 다양한 해상도 자동 인코딩
- **📸 자동 썸네일 생성**: 비디오에서 자동으로 썸네일 추출
- **🎭 스프라이트 시트 생성**: 비디오 스크러빙용 프리뷰 이미지
- **☁️ 청크 업로드**: 대용량 파일 안정적 업로드
- **🔄 큐 기반 처리**: 비동기 백그라운드 처리
- **⚡ Job 체이닝**: 순차적 비디오 처리 파이프라인
- **🖥️ Orchid 통합**: 완전한 관리자 인터페이스
- **📱 Entities 아키텍처**: CMS-Orbit 호환 구조

## 🚀 설치

### 1. Composer 설치
```bash
composer require cms-orbit/video
```

### 2. ServiceProvider 등록 (Laravel 11+)
```php
// bootstrap/providers.php
return [
    // ...
    CmsOrbit\VideoField\VideoServiceProvider::class,
];
```

### 3. 설정 파일 발행
```bash
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="video-config"
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="video-migrations"
php artisan vendor:publish --provider="CmsOrbit\VideoField\VideoServiceProvider" --tag="video-lang"
```

### 4. 마이그레이션 실행
```bash
php artisan migrate
```

### 5. FFmpeg 설치 및 설정
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install ffmpeg

# macOS (Homebrew)
brew install ffmpeg

# 설정 파일에서 경로 설정
# config/video.php
'ffmpeg' => [
    'binary_path' => env('FFMPEG_BINARY_PATH', 'ffmpeg'),
    'ffprobe_path' => env('FFPROBE_BINARY_PATH', 'ffprobe'),
],
```

## ⚙️ 설정

### 환경 변수 설정
```env
# .env 파일
FFMPEG_BINARY_PATH=/usr/bin/ffmpeg
FFPROBE_BINARY_PATH=/usr/bin/ffprobe
VIDEO_STORAGE_PATH=videos/{videoId}
VIDEO_THUMBNAILS_PATH=videos/{videoId}/thumbnails
VIDEO_SPRITES_PATH=videos/{videoId}/sprites
MEDIA_DISK=public
VIDEO_QUEUE_NAME=encode_video
QUEUE_CONNECTION=redis
```

### 큐 워커 실행
```bash
# 비디오 처리 전용 큐 워커
php artisan queue:work --queue=encode_video

# 또는 모든 큐
php artisan queue:work
```

## 📋 사용법

### 1. 모델에 HasVideoField Trait 추가
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use CmsOrbit\VideoField\Traits\HasVideoField;

class Post extends Model
{
    use HasVideoField;

    // 비디오 필드 설정
    protected $videoFields = [
        'featured_video' => [
            'profiles' => ['HD@30fps', 'FHD@30fps'], // 커스텀 프로파일
            'auto_thumbnail' => true,
            'auto_sprite' => true,
        ],
        'gallery_video' => [
            'profiles' => ['HD@30fps'], // 갤러리용은 HD만
            'auto_thumbnail' => true,
            'auto_sprite' => false,
        ],
    ];
}
```

### 2. 비디오 업로드 API 사용
```javascript
// 프론트엔드에서 청크 업로드
const uploadVideo = async (file) => {
    const chunkSize = 1024 * 1024; // 1MB chunks
    const totalChunks = Math.ceil(file.size / chunkSize);
    const uploadId = generateUUID();

    for (let i = 0; i < totalChunks; i++) {
        const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);
        const formData = new FormData();
        
        formData.append('chunk', chunk);
        formData.append('chunk_number', i);
        formData.append('total_chunks', totalChunks);
        formData.append('upload_id', uploadId);
        formData.append('filename', file.name);

        await fetch('/api/video/upload/chunk', {
            method: 'POST',
            body: formData
        });
    }

    // 업로드 완료
    const response = await fetch('/api/video/upload/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            upload_id: uploadId,
            filename: file.name,
            total_chunks: totalChunks
        })
    });

    return response.json();
};
```

### 3. 비디오 첨부
```php
// 컨트롤러에서
$post = Post::create($request->validated());

// 비디오 첨부
$post->attachVideo('featured_video', $videoId);

// 또는 여러 비디오 첨부
$post->attachVideos('gallery_video', [$videoId1, $videoId2]);
```

### 4. 비디오 출력
```php
// 비디오 URL 가져오기
$video = $post->getVideo('featured_video');
$videoUrl = $video?->getUrl('FHD@30fps'); // 특정 프로파일
$thumbnailUrl = $video?->getThumbnailUrl();
$spriteUrl = $video?->getSpriteUrl();

// 사용 가능한 프로파일 확인
$profiles = $video?->getAvailableProfiles();
// ['HD@30fps', 'FHD@30fps', '4K@60fps']
```

### 5. Blade 템플릿에서 사용
```blade
{{-- 단일 비디오 --}}
@if($post->hasVideo('featured_video'))
    @php($video = $post->getVideo('featured_video'))
    <video controls poster="{{ $video->getThumbnailUrl() }}">
        @foreach($video->getAvailableProfiles() as $profile)
            <source src="{{ $video->getUrl($profile) }}" 
                    type="video/mp4" 
                    label="{{ $profile }}">
        @endforeach
    </video>
@endif

{{-- 비디오 갤러리 --}}
@foreach($post->getVideos('gallery_video') as $video)
    <div class="video-item">
        <img src="{{ $video->getThumbnailUrl() }}" 
             data-video="{{ $video->getUrl() }}"
             data-sprite="{{ $video->getSpriteUrl() }}"
             class="video-thumbnail">
    </div>
@endforeach
```

## 🎯 CLI 커맨드

패키지는 강력한 CLI 커맨드들을 제공합니다:

### 비디오 인코딩
```bash
# 특정 비디오 인코딩
php artisan video:encode 1

# 모든 대기중인 비디오 인코딩
php artisan video:encode

# 특정 프로파일만 인코딩
php artisan video:encode 1 --profile="FHD@30fps"

# 강제 재인코딩
php artisan video:encode 1 --force
```

### 썸네일 생성
```bash
# 특정 비디오의 썸네일 생성 (5초 지점)
php artisan video:thumbnail 1

# 특정 시간대 썸네일 생성
php artisan video:thumbnail 1 --time=10

# 모든 비디오의 썸네일 생성
php artisan video:thumbnail --all
```

### 스프라이트 시트 생성
```bash
# 기본 설정으로 스프라이트 생성 (100프레임, 10x10)
php artisan video:sprite 1

# 커스텀 설정으로 스프라이트 생성
php artisan video:sprite 1 --frames=50 --columns=5 --rows=10

# 모든 비디오의 스프라이트 생성
php artisan video:sprite --all
```

### 통합 처리
```bash
# 모든 과정을 한번에 (인코딩 + 썸네일 + 스프라이트)
php artisan video:process-all 1

# 모든 대기중인 비디오 처리
php artisan video:process-all
```

## 🔧 고급 설정

### 커스텀 인코딩 프로파일
```php
// config/video.php
'profiles' => [
    'mobile' => [
        'width' => 480,
        'height' => 270,
        'bitrate' => '500k',
        'framerate' => 30,
        'codec' => 'libx264',
    ],
    'desktop' => [
        'width' => 1920,
        'height' => 1080,
        'bitrate' => '2M',
        'framerate' => 30,
        'codec' => 'libx264',
    ],
    'premium' => [
        'width' => 3840,
        'height' => 2160,
        'bitrate' => '8M',
        'framerate' => 60,
        'codec' => 'libx265', // H.265 for better compression
    ],
],
```

### 썸네일 설정
```php
'thumbnails' => [
    'quality' => 85,
    'format' => 'jpeg', // jpeg, webp
    'time_position' => '00:00:05', // 5초 지점
],
```

### 스프라이트 설정
```php
'sprites' => [
    'width' => 160,
    'height' => 90,
    'interval' => 10, // 10초 간격
    'quality' => 70,
    'format' => 'jpeg',
],
```

## 🔗 API 엔드포인트

### 비디오 관리
- `GET /api/videos` - 비디오 목록
- `GET /api/videos/{id}` - 비디오 상세
- `POST /api/video/upload/chunk` - 청크 업로드
- `POST /api/video/upload/complete` - 업로드 완료
- `DELETE /api/video/upload/cancel` - 업로드 취소

### 응답 예시
```json
{
    "id": 1,
    "title": "샘플 비디오",
    "original_filename": "sample.mp4",
    "duration": 120.5,
    "status": "completed",
    "thumbnail_path": "videos/1/thumbnails/thumbnail.jpeg",
    "profiles": [
        {
            "profile": "HD@30fps",
            "file_size": 52428800,
            "status": "completed"
        },
        {
            "profile": "FHD@30fps", 
            "file_size": 104857600,
            "status": "completed"
        }
    ]
}
```

## 📊 데이터베이스 구조

패키지는 4개의 주요 테이블을 생성합니다:

- **videos**: 비디오 메인 정보 (제목, 설명, 원본 파일 등)
- **video_profiles**: 프로파일별 인코딩된 파일 정보
- **video_encoding_logs**: 인코딩 프로세스 로그
- **video_field_relations**: 엔티티와 비디오 간의 관계

## 🎯 Job 아키텍처

모든 비디오 처리는 큐 기반으로 동작합니다:

### Job 흐름
```
VideoUpload (완료)
       ↓
VideoProcessJob (메인 Job)
       ↓
┌─────────────────────┐
│  Job Chain 시작      │
└─────────────────────┘
       ↓
VideoEncodeJob (1단계)
  ├─ FFmpeg 인코딩
  ├─ 다중 프로파일 처리
  └─ 메타데이터 추출
       ↓
VideoThumbnailJob (2단계)
  ├─ 썸네일 생성
  └─ 이미지 최적화
       ↓
VideoSpriteJob (3단계)
  ├─ 스프라이트 시트 생성
  └─ 스크러빙 프리뷰
```

### Job 특징
- **Sequential Processing**: 순차적 체인 처리
- **Error Handling**: 각 단계별 에러 처리
- **Retry Logic**: 실패시 자동 재시도
- **Progress Tracking**: 실시간 진행 상황 추적
- **Resource Management**: 메모리/CPU 효율적 사용

### 커맨드 vs Job 구조
```php
// CLI 커맨드는 Job을 dispatch만 함
php artisan video:encode 1
    ↓
VideoEncodeCommand::handle()
    ↓
dispatch(new VideoEncodeJob($video))

// 실제 로직은 Job에서 처리
VideoEncodeJob::handle()
    ↓ 
FFmpeg 인코딩 실행
```

## 🔄 개발 로드맵

### ✅ Phase 1: 기본 구조 (완료)
- [x] 패키지 ServiceProvider 설정
- [x] 기본 설정 파일 생성
- [x] Entities 기반 Video 엔티티 생성
- [x] VideoProfile, VideoEncodingLog 모델 생성
- [x] 마이그레이션 파일 작성
- [x] HasVideoField Trait 구현
- [x] Orchid 관리자 화면 통합
- [x] Path {videoId} placeholder 시스템

### ✅ Phase 2: HasVideoField Trait 개발 (완료)
- [x] 기본 프로파일 정의
- [x] 비디오 필드 관계 설정
- [x] 모델별 커스텀 프로파일 override 기능
- [x] Helper 메서드 구현 (getVideo, getVideoUrl)

### ✅ Phase 3: 업로드 시스템 (완료)
- [x] 파일 업로드 API 구현
- [x] 청크 업로드 지원 (대용량 파일)
- [x] 파일 검증 및 메타데이터 추출
- [x] Video 엔티티 자동 생성
- [x] 임시 파일 관리 시스템

### ✅ Phase 4: FFmpeg 인코딩 시스템 (완료)
- [x] FFmpeg 래퍼 클래스 개발
- [x] 프로파일별 인코딩 Job 구현
- [x] encode_video 큐 시스템 설정
- [x] 인코딩 진행률 추적
- [x] 로그 시스템 구현
- [x] 에러 핸들링 및 재시도 로직

### ✅ Phase 5: 썸네일 및 스프라이트 생성 (완료)
- [x] 썸네일 자동 추출
- [x] 스크러빙용 스프라이트 시트 생성
- [x] 다양한 크기 지원
- [x] JPEG/WebP 포맷 최적화
- [x] CLI 커맨드 구현

### ✅ Phase 6: Job 시스템 리팩토링 (완료)
- [x] VideoEncodeJob, VideoThumbnailJob, VideoSpriteJob 생성
- [x] VideoProcessJob으로 체이닝 통합
- [x] 커맨드를 Job dispatch로 변경
- [x] 업로드 완료시 자동 Job 실행
- [x] 큐 기반 비동기 처리 완성

### 📋 향후 계획
- Phase 7: 오키드 관리자 화면 고도화
- Phase 8: API 및 스트리밍 최적화
- Phase 9: 테스트 및 최적화

## 🛠️ 트러블슈팅

### FFmpeg 관련
```bash
# FFmpeg 설치 확인
ffmpeg -version

# 권한 문제 해결
sudo chmod +x /usr/bin/ffmpeg

# 경로 설정 확인
which ffmpeg
```

### 큐 관련
```bash
# 큐 워커 상태 확인
php artisan queue:work --queue=encode_video --verbose

# 실패한 Job 확인
php artisan queue:failed

# 실패한 Job 재시도
php artisan queue:retry all
```

### 저장소 권한
```bash
# 저장소 디렉토리 권한 설정
sudo chown -R www-data:www-data storage/
sudo chmod -R 755 storage/
```

## 📝 라이센스

MIT 라이센스. 자세한 내용은 [LICENSE](LICENSE) 파일을 참조하세요.

## 🤝 기여

기여는 언제나 환영합니다! 이슈나 풀 리퀘스트를 통해 참여해 주세요.

## 📞 지원

- 📧 이메일: support@amuz.co.kr
- 📚 문서: [CMS-Orbit 문서](https://docs.cms-orbit.com)
- 🐛 버그 리포트: [GitHub Issues](https://github.com/cms-orbit/video/issues)
