# Video Player API 문서

## 개요
VideoPlayerApiController는 프론트엔드 비디오 플레이어를 위한 전용 API를 제공합니다. 비디오 재생, 이벤트 추적, 분석 데이터 등을 처리합니다.

## API 엔드포인트

### 기본 URL
```
/api/orbit-video-player
```

### 인증
- 대부분의 엔드포인트는 인증이 필요하지 않습니다 (게스트 사용자도 시청 가능)
- 분석 데이터는 관리자 인증이 필요합니다

---

## 1. 비디오 정보 조회

프론트엔드 플레이어를 위한 비디오 상세 정보를 가져옵니다.

### 엔드포인트
```
GET /api/orbit-video-player/{id}
```

### 파라미터
- `id` (필수): 비디오 ID

### 응답 예시
```json
{
    "id": 1,
    "title": "Sample Video",
    "filename": "sample-video.mp4",
    "duration": 120,
    "file_size": 52428800,
    "status": "completed",
    "thumbnail_url": "https://example.com/thumbnail.jpg",
    "sprite_url": "https://example.com/sprite.jpg",
    "sprite_metadata": {
        "sprite": {
            "path": "videos/1/sprites/sprite.jpg",
            "columns": 10,
            "rows": 10,
            "thumbnail_width": 160,
            "thumbnail_height": 90,
            "interval": 1
        }
    },
    "created_at": "2024-01-01T00:00:00.000000Z",
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
            "url": "https://example.com/1080p.mp4",
            "width": 1920,
            "height": 1080
        }
    ]
}
```

---

## 2. 재생 시작 이벤트

비디오 재생이 시작될 때 호출합니다.

### 엔드포인트
```
POST /api/orbit-video-player/{id}/play
```

### 파라미터
- `id` (필수): 비디오 ID

### 요청 바디
```json
{
    "timestamp": 0,
    "quality": "1080p"
}
```

### 응답 예시
```json
{
    "success": true,
    "message": "Play event recorded"
}
```

### 구현 예정 기능
- 사용자 ID 기록 (로그인한 경우)
- IP 주소 및 User Agent 기록
- 재생 시작 시간 기록
- 선택된 화질 기록

---

## 3. 일시정지 이벤트

비디오가 일시정지될 때 호출합니다.

### 엔드포인트
```
POST /api/orbit-video-player/{id}/pause
```

### 파라미터
- `id` (필수): 비디오 ID

### 요청 바디
```json
{
    "timestamp": 45.5,
    "duration": 45.5
}
```

### 응답 예시
```json
{
    "success": true,
    "message": "Pause event recorded"
}
```

### 구현 예정 기능
- 일시정지 시점 기록
- 실제 재생된 시간 기록

---

## 4. 재생 진행률 기록

주기적으로 재생 진행률을 기록합니다.

### 엔드포인트
```
POST /api/orbit-video-player/{id}/progress
```

### 파라미터
- `id` (필수): 비디오 ID

### 요청 바디
```json
{
    "timestamp": 60.0,
    "percentage": 50.0
}
```

### 응답 예시
```json
{
    "success": true,
    "message": "Progress recorded"
}
```

### 구현 예정 기능
- 현재 재생 위치 기록
- 시청 완료 비율 추적
- 세션별 시청 이력

---

## 5. 재생 완료 이벤트

비디오 시청이 완료되었을 때 호출합니다.

### 엔드포인트
```
POST /api/orbit-video-player/{id}/complete
```

### 파라미터
- `id` (필수): 비디오 ID

### 요청 바디
```json
{
    "watched_duration": 120.0,
    "completed_percentage": 95.0
}
```

### 응답 예시
```json
{
    "success": true,
    "message": "Complete event recorded"
}
```

### 구현 예정 기능
- 총 시청 시간 기록
- 완료 비율 기록
- 시청 완료 여부 판단 (90% 이상 시청 등)

---

## 6. 재생 위치 저장/불러오기

사용자의 마지막 재생 위치를 저장하고 불러옵니다.

### 재생 위치 불러오기
```
GET /api/orbit-video-player/{id}/position
```

#### 응답 예시
```json
{
    "position": 45.5
}
```

### 재생 위치 저장
```
POST /api/orbit-video-player/{id}/position
```

#### 요청 바디
```json
{
    "position": 45.5
}
```

#### 응답 예시
```json
{
    "success": true,
    "position": 45.5,
    "message": "Position saved"
}
```

### 구현 예정 기능
- 사용자별 마지막 재생 위치 저장
- 로그인하지 않은 경우 세션/로컬스토리지 활용
- 여러 디바이스 간 동기화

---

## 7. 조회수 증가

비디오 조회수를 증가시킵니다.

### 엔드포인트
```
POST /api/orbit-video-player/{id}/view
```

### 파라미터
- `id` (필수): 비디오 ID

### 응답 예시
```json
{
    "success": true,
    "message": "View count incremented"
}
```

### 구현 예정 기능
- 중복 조회 방지 (IP, 세션, 시간 기반)
- 조회수 캐싱
- 비동기 큐 처리

---

## 8. 문제 리포트

재생 중 발생한 문제를 리포트합니다.

### 엔드포인트
```
POST /api/orbit-video-player/{id}/report-issue
```

### 파라미터
- `id` (필수): 비디오 ID

### 요청 바디
```json
{
    "issue_type": "buffering",
    "description": "Video keeps buffering at 1080p quality",
    "timestamp": 45.5,
    "quality_profile": "1080p"
}
```

#### 필드 설명
- `issue_type` (필수): 문제 유형
  - `quality`: 화질 문제
  - `buffering`: 버퍼링 문제
  - `audio`: 오디오 문제
  - `subtitle`: 자막 문제
  - `other`: 기타 문제
- `description` (선택): 문제 상세 설명 (최대 500자)
- `timestamp` (선택): 문제 발생 시점 (초)
- `quality_profile` (선택): 재생 중이던 화질

### 응답 예시
```json
{
    "success": true,
    "message": "Issue report submitted"
}
```

### 구현 예정 기능
- 문제 유형별 분류
- 발생 시점 기록
- 사용자 환경 정보 수집 (브라우저, OS 등)
- 로그 데이터베이스 저장

---

## 9. 분석 데이터 조회 (관리자)

비디오의 시청 분석 데이터를 조회합니다.

### 엔드포인트
```
GET /api/orbit-video-player/{id}/analytics
```

### 파라미터
- `id` (필수): 비디오 ID

### 인증
- 관리자 권한 필요 (`auth` 미들웨어)

### 응답 예시
```json
{
    "total_views": 1250,
    "average_watch_time": 85.5,
    "completion_rate": 68.5,
    "quality_distribution": {
        "1080p": 45.2,
        "720p": 32.8,
        "480p": 15.5,
        "360p": 6.5
    }
}
```

### 구현 예정 기능
- 총 재생 횟수
- 평균 시청 시간
- 시청 완료율
- 화질별 선택 비율
- 시간대별 재생 패턴
- 지역별 통계
- 디바이스별 통계

---

## Vue 컴포넌트 통합 예시

### 재생 시작 이벤트
```javascript
const handlePlay = async () => {
    try {
        await axios.post(`/api/orbit-video-player/${videoId}/play`, {
            timestamp: videoElement.value.currentTime,
            quality: currentQuality.value
        });
    } catch (error) {
        console.error('Failed to record play event:', error);
    }
};
```

### 진행률 추적
```javascript
const trackProgress = async () => {
    const currentTime = videoElement.value.currentTime;
    const duration = videoElement.value.duration;
    const percentage = (currentTime / duration) * 100;

    try {
        await axios.post(`/api/orbit-video-player/${videoId}/progress`, {
            timestamp: currentTime,
            percentage: percentage
        });
    } catch (error) {
        console.error('Failed to record progress:', error);
    }
};

// 5초마다 진행률 기록
setInterval(trackProgress, 5000);
```

### 재생 위치 복원
```javascript
const restorePosition = async () => {
    try {
        const response = await axios.get(`/api/orbit-video-player/${videoId}/position`);
        if (response.data.position > 0) {
            videoElement.value.currentTime = response.data.position;
        }
    } catch (error) {
        console.error('Failed to restore position:', error);
    }
};
```

---

## 구현 가이드

### 1. 이벤트 로깅 테이블 생성
```php
// database/migrations/xxxx_create_video_playback_events_table.php
Schema::create('video_playback_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('video_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
    $table->string('session_id')->nullable();
    $table->ipAddress('ip_address');
    $table->string('user_agent')->nullable();
    $table->enum('event_type', ['play', 'pause', 'complete', 'progress']);
    $table->decimal('timestamp', 10, 2)->nullable();
    $table->decimal('duration', 10, 2)->nullable();
    $table->decimal('percentage', 5, 2)->nullable();
    $table->string('quality')->nullable();
    $table->timestamps();
    
    $table->index(['video_id', 'event_type']);
    $table->index(['user_id', 'video_id']);
    $table->index('created_at');
});
```

### 2. 조회수 테이블 생성
```php
// database/migrations/xxxx_create_video_views_table.php
Schema::create('video_views', function (Blueprint $table) {
    $table->id();
    $table->foreignId('video_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
    $table->ipAddress('ip_address');
    $table->string('session_id');
    $table->timestamp('viewed_at');
    
    $table->unique(['video_id', 'ip_address', 'session_id']);
    $table->index('viewed_at');
});
```

### 3. 재생 위치 테이블 생성
```php
// database/migrations/xxxx_create_video_positions_table.php
Schema::create('video_positions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('video_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
    $table->string('session_id')->nullable();
    $table->decimal('position', 10, 2);
    $table->timestamps();
    
    $table->unique(['video_id', 'user_id']);
    $table->unique(['video_id', 'session_id']);
});
```

### 4. 문제 리포트 테이블 생성
```php
// database/migrations/xxxx_create_video_issue_reports_table.php
Schema::create('video_issue_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('video_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
    $table->enum('issue_type', ['quality', 'buffering', 'audio', 'subtitle', 'other']);
    $table->text('description')->nullable();
    $table->decimal('timestamp', 10, 2)->nullable();
    $table->string('quality_profile')->nullable();
    $table->string('user_agent')->nullable();
    $table->ipAddress('ip_address');
    $table->timestamps();
    
    $table->index(['video_id', 'issue_type']);
    $table->index('created_at');
});
```

---

## 성능 최적화

### 캐싱
- 조회수는 Redis에 캐싱하고 배치로 DB에 저장
- 분석 데이터는 일일 단위로 집계하여 캐싱

### 비동기 처리
- 모든 이벤트 로깅은 큐로 처리
- 실시간성이 필요하지 않은 데이터는 배치 처리

### 인덱싱
- 자주 조회되는 필드에 인덱스 생성
- 복합 인덱스로 쿼리 성능 향상

---

## 보안 고려사항

### Rate Limiting
- IP 기반 요청 제한
- 과도한 이벤트 로깅 방지

### 데이터 검증
- 모든 입력값 검증
- SQL Injection 방지
- XSS 방지

### 개인정보 보호
- IP 주소 해싱 저장 고려
- GDPR 준수
- 개인정보 보관 기간 정책

---

## 다음 단계

1. **마이그레이션 파일 생성**: 위의 테이블 구조로 마이그레이션 생성
2. **모델 생성**: 각 테이블에 대한 Eloquent 모델 생성
3. **Job 생성**: 이벤트 로깅을 위한 큐 Job 생성
4. **Service 클래스**: 비즈니스 로직을 Service 클래스로 분리
5. **테스트 작성**: API 엔드포인트에 대한 테스트 작성
6. **문서화**: API 문서 업데이트 및 예제 추가

---

## 라이센스
이 API는 CMS Orbit 프로젝트의 일부입니다.

