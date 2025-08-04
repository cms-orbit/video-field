# CMS-Orbit Video Field Package 개발 계획서

## 1. 프로젝트 개요

### 1.1 목적
CMS-Orbit 엔티티를 위한 비디오 필드 시스템 개발 - Trait 기반으로 간편하게 비디오 필드 추가 및 다중 프로파일 인코딩 지원

### 1.2 핵심 가치
- **간편성**: HasVideoField trait로 한 줄 설정
- **유연성**: 모델별 커스텀 프로파일 설정
- **확장성**: 다중 프로파일 자동 인코딩
- **관리성**: 오키드 통합 관리자 화면

### 1.3 사용 예시
```php
class Announcement extends DynamicModel
{
    use HasVideoField;
    
    protected $videoFields = ['opening', 'video1', 'video2'];
    
    // 선택사항: 커스텀 프로파일 정의
    protected function getVideoProfiles(): array
    {
        return [
            'FHD@30fps' => ['width' => 1920, 'height' => 1080, 'framerate' => 30, 'bitrate' => '8M'],
            'HD@30fps' => ['width' => 1280, 'height' => 720, 'framerate' => 30, 'bitrate' => '4M'],
        ];
    }
}
```

## 2. 시스템 아키텍처

### 2.1 비디오 업로드
- ✅ 청크 기반 업로드 (대용량 파일 지원)
- ✅ 다중 파일 업로드
- ✅ 드래그 앤 드롭 인터페이스
- ✅ 업로드 진행률 실시간 표시
- ✅ 업로드 일시정지/재개 기능
- ✅ 파일 유효성 검사 (포맷, 크기, 코덱)

### 2.2 비디오 인코딩
- ✅ FFmpeg 기반 트랜스코딩
- ✅ 다중 해상도 지원 (240p ~ 4K)
- ✅ 적응형 비트레이트 스트리밍 (ABR)
- ✅ HLS/DASH 스트리밍 포맷
- ✅ 비디오 압축 최적화
- ✅ 오디오 트랙 분리 및 최적화

### 2.3 데이터 구조

#### Video 엔티티 (DynamicModel)
- 메인 비디오 정보 저장
- 썸네일 이미지
- 스크러빙 썸네일 (스프라이트 시트)
- 총 재생 시간
- 원본 파일 정보

#### VideoProfile 모델 (hasMany)
- 프로파일별 인코딩된 파일 경로
- 인코딩 상태 관리
- UUID 기반 식별

### 2.4 핵심 컴포넌트

#### HasVideoField Trait
- 비디오 필드 자동 관리
- 기본 프로파일 정의
- 모델별 커스텀 프로파일 override
- 자동 인코딩 큐 관리

#### 프로파일 시스템
```php
기본 프로파일:
- 4K@60fps: 3840x2160, 60fps, 15M bitrate
- 4K@30fps: 3840x2160, 30fps, 10M bitrate  
- FHD@60fps: 1920x1080, 60fps, 12M bitrate
- FHD@30fps: 1920x1080, 30fps, 8M bitrate
- HD@30fps: 1280x720, 30fps, 4M bitrate
- SD@30fps: 640x480, 30fps, 2M bitrate
```

### 2.5 워크플로우
1. **업로드**: 비디오 파일 업로드 → Video 엔티티 생성 (대용량 청크업로드 및 검증 포함)
2. **큐잉**: encode_video 큐에 인코딩 작업 추가
3. **인코딩**: 각 프로파일별 FFmpeg 인코딩 실행
4. **완료**: VideoProfile 레코드의 encoded 필드 true로 업데이트
5. **제공**: 프로파일명으로 비디오 스트리밍

## 3. 데이터베이스 설계

### 3.1 테이블 구조

#### videos 테이블 (DynamicModel 기반)
```sql
- id (Primary Key)
- uuid (Unique Identifier)
- title (비디오 제목)
- description (설명)
- original_filename (원본 파일명)
- original_size (원본 파일 크기)
- duration (총 재생 시간, 초)
- thumbnail_path (썸네일 이미지 경로)
- scrubbing_sprite_path (스크러빙용 스프라이트 시트 경로)
- sprite_columns (스프라이트 열 개수)
- sprite_rows (스프라이트 행 개수)
- sprite_interval (스프라이트 간격, 초)
- mime_type (MIME 타입)
- status (처리 상태: pending, processing, completed, failed)
- user_id (업로더 ID)
- meta_data (추가 메타데이터 JSON)
- created_at, updated_at
```

#### video_profiles 테이블
```sql
- id (Primary Key)
- video_id (Foreign Key to videos)
- uuid (Unique Identifier)
- field (필드명: opening, video1, video2 등)
- profile (프로파일명: 4K@60fps, FHD@30fps 등)
- path (인코딩된 파일 경로)
- encoded (인코딩 완료 여부: 0/1)
- file_size (파일 크기)
- width (가로 해상도)
- height (세로 해상도)
- framerate (프레임레이트)
- bitrate (비트레이트)
- created_at, updated_at
```

#### video_encoding_logs 테이블
```sql
- id (Primary Key)
- video_profile_id (Foreign Key to video_profiles)
- status (로그 상태: started, progress, completed, error)
- message (로그 메시지)
- progress (진행률, 0-100)
- ffmpeg_command (실행된 FFmpeg 명령어)
- error_output (에러 출력)
- processing_time (처리 시간, 초)
- created_at
```

### 3.2 관계 설정
- Video hasMany VideoProfiles
- VideoProfile hasMany VideoEncodingLogs
- 각 엔티티 모델 belongsToMany Videos (through pivot table)

## 4. 핵심 컴포넌트 설계

### 4.1 HasVideoField Trait
```php
trait HasVideoField
{
    protected $defaultVideoProfiles = [
        '4K@60fps' => ['width' => 3840, 'height' => 2160, 'framerate' => 60, 'bitrate' => '15M', 'profile' => 'main10', 'level' => '5.1'],
        '4K@30fps' => ['width' => 3840, 'height' => 2160, 'framerate' => 30, 'bitrate' => '10M', 'profile' => 'main10', 'level' => '5.0'],
        'FHD@60fps' => ['width' => 1920, 'height' => 1080, 'framerate' => 60, 'bitrate' => '12M', 'profile' => 'main', 'level' => '4.1'],
        'FHD@30fps' => ['width' => 1920, 'height' => 1080, 'framerate' => 30, 'bitrate' => '8M', 'profile' => 'main', 'level' => '4.0'],
        'HD@30fps' => ['width' => 1280, 'height' => 720, 'framerate' => 30, 'bitrate' => '4M', 'profile' => 'main', 'level' => '3.1'],
        'SD@30fps' => ['width' => 640, 'height' => 480, 'framerate' => 30, 'bitrate' => '2M', 'profile' => 'main', 'level' => '3.0'],
    ];
    
    // 모델별 커스텀 프로파일 override 가능
    protected function getVideoProfiles(): array
    {
        return $this->defaultVideoProfiles;
    }
    
    // 비디오 필드 관계 설정
    public function videoFields()
    {
        return $this->belongsToMany(Video::class, 'video_field_relations')
                    ->withPivot(['field_name', 'sort_order'])
                    ->wherePivot('model_type', static::class);
    }
    
    // 특정 필드의 비디오 가져오기
    public function getVideo(string $field): ?Video
    {
        return $this->videoFields()->wherePivot('field_name', $field)->first();
    }
    
    // 비디오 스트리밍 URL 생성
    public function getVideoUrl(string $field, string $profile = 'FHD@30fps'): ?string
    {
        $video = $this->getVideo($field);
        return $video?->getStreamingUrl($profile);
    }
}
```

### 4.2 Video 모델 (DynamicModel)
```php
class Video extends DynamicModel
{
    public function profiles()
    {
        return $this->hasMany(VideoProfile::class);
    }
    
    public function encodingLogs()
    {
        return $this->hasManyThrough(VideoEncodingLog::class, VideoProfile::class);
    }
    
    public function getStreamingUrl(string $profile): ?string
    {
        $videoProfile = $this->profiles()->where('profile', $profile)->where('encoded', true)->first();
        return $videoProfile ? Storage::url($videoProfile->path) : null;
    }
    
    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }
    
    public function getScrubbiingSpriteUrl(): ?string
    {
        return $this->scrubbing_sprite_path ? Storage::url($this->scrubbing_sprite_path) : null;
    }
}
```

### 4.3 인코딩 큐 시스템
- Queue: encode_video
- Job: EncodeVideoProfile
- 각 프로파일별 개별 Job으로 분리하여 병렬 처리

## 5. 오키드 관리자 화면 구성 제안

### 5.1 메인 비디오 관리 스크린
```php
// VideoListScreen.php
- 비디오 목록 (제목, 상태, 업로드 날짜, 파일 크기)
- 필터링: 상태별, 날짜별, 업로더별
- 검색: 제목, 설명, 파일명
- 일괄 작업: 삭제, 재인코딩, 상태 변경
- 인코딩 진행률 표시 (프로그레스 바)
```

### 5.2 비디오 상세/편집 스크린
```php
// VideoEditScreen.php
- 기본 정보 편집 (제목, 설명, 태그)
- 썸네일 미리보기 및 변경
- 프로파일별 인코딩 상태 표시
- 스크러빙 스프라이트 미리보기
- 비디오 플레이어 (원본 및 인코딩된 버전)
- 메타데이터 정보 (해상도, 코덱, 비트레이트 등)
```

### 5.3 인코딩 모니터링 대시보드
```php
// EncodingMonitorScreen.php
- 실시간 인코딩 진행률
- 큐 상태 (대기중, 처리중, 완료, 실패)
- 워커 상태 모니터링
- 인코딩 로그 뷰어
- 실패한 작업 재시작 기능
- 시스템 리소스 사용량 (CPU, 메모리, 디스크)
```

### 5.4 프로파일 관리 스크린
```php
// ProfileManagementScreen.php
- 전역 기본 프로파일 설정
- 엔티티별 커스텀 프로파일 조회
- 프로파일 생성/수정/삭제
- 프로파일 성능 통계 (파일 크기, 인코딩 시간)
- 프로파일 사용량 분석
```

### 5.5 통계 및 보고서 스크린
```php
// VideoStatsScreen.php
- 업로드 통계 (일별, 월별)
- 스토리지 사용량 차트
- 인코딩 성공률
- 평균 인코딩 시간
- 프로파일별 사용량
- 사용자별 업로드 통계
```

### 5.6 설정 스크린
```php
// VideoSettingsScreen.php
- FFmpeg 경로 설정
- 스토리지 설정 (Local, S3, CDN)
- 큐 설정 (Redis, Database)
- 업로드 제한 설정
- 자동 정리 설정
- 알림 설정
```

## 6. 개발 단계별 계획

### Phase 1: 기본 구조 (1주)
- [ ] 패키지 ServiceProvider 설정
- [ ] 기본 설정 파일 생성
- [ ] DynamicModel 기반 Video 엔티티 생성
- [ ] VideoProfile, VideoEncodingLog 모델 생성
- [ ] 마이그레이션 파일 작성

### Phase 2: HasVideoField Trait 개발 (1주)
- [ ] 기본 프로파일 정의
- [ ] 비디오 필드 관계 설정
- [ ] 모델별 커스텀 프로파일 override 기능
- [ ] Helper 메서드 구현 (getVideo, getVideoUrl)
- [ ] 테스트 케이스 작성

### Phase 3: 업로드 시스템 (1-2주)
- [ ] 파일 업로드 API 구현
- [ ] 청크 업로드 지원 (대용량 파일)
- [ ] 파일 검증 및 메타데이터 추출
- [ ] Video 엔티티 자동 생성
- [ ] 임시 파일 관리 시스템

### Phase 4: FFmpeg 인코딩 시스템 (2주)
- [ ] FFmpeg 래퍼 클래스 개발
- [ ] 프로파일별 인코딩 Job 구현
- [ ] encode_video 큐 시스템 설정
- [ ] 인코딩 진행률 추적
- [ ] 로그 시스템 구현
- [ ] 에러 핸들링 및 재시도 로직

### Phase 5: 썸네일 및 스프라이트 생성 (1주)
- [ ] 썸네일 자동 추출
- [ ] 스크러빙용 스프라이트 시트 생성
- [ ] 다양한 크기 지원
- [ ] WebP 포맷 최적화

### Phase 6: 오키드 관리자 화면 (2주)
- [ ] VideoListScreen 구현
- [ ] VideoEditScreen 구현
- [ ] EncodingMonitorScreen 구현
- [ ] ProfileManagementScreen 구현
- [ ] VideoStatsScreen 구현
- [ ] VideoSettingsScreen 구현

### Phase 7: API 및 스트리밍 (1주)
- [ ] RESTful API 엔드포인트
- [ ] 스트리밍 URL 생성
- [ ] HLS/DASH 지원 (선택사항)
- [ ] CDN 연동
- [ ] 캐싱 최적화

### Phase 8: 테스트 및 최적화 (1주)
- [ ] 단위 테스트 작성
- [ ] 통합 테스트
- [ ] 성능 테스트
- [ ] 메모리 최적화
- [ ] 문서화

## 7. 기술 스택

### 7.1 백엔드
- **Laravel 11**: 프레임워크
- **FFmpeg**: 비디오 인코딩
- **Redis**: 큐 시스템
- **GD/Imagick**: 이미지 처리
- **Laravel Horizon**: 큐 모니터링

### 7.2 데이터베이스
- **MySQL/PostgreSQL**: 메인 데이터베이스
- **Redis**: 캐싱 및 세션

### 7.3 스토리지
- **Local**: 개발 환경
- **S3/GCS/Azure**: 프로덕션 환경
- **CDN**: 전송 최적화

## 8. 보안 및 성능

### 8.1 보안 고려사항
- [ ] 파일 타입 검증 (MIME type, 확장자)
- [ ] 파일 크기 제한
- [ ] 업로드 속도 제한
- [ ] 사용자 권한 검증
- [ ] 악성 파일 스캔 (선택사항)

### 8.2 성능 최적화
- [ ] 청크 업로드로 메모리 사용량 최적화
- [ ] 병렬 인코딩 처리
- [ ] CDN 활용
- [ ] 캐싱 전략
- [ ] 데이터베이스 인덱싱

## 9. 모니터링 및 로깅

### 9.1 로깅 시스템
- [ ] 업로드 이벤트 로깅
- [ ] 인코딩 프로세스 로깅
- [ ] 에러 추적 시스템
- [ ] 성능 메트릭 수집

### 9.2 알림 시스템
- [ ] 인코딩 완료 알림
- [ ] 에러 발생 알림
- [ ] 시스템 상태 알림

## 10. 테스트 전략

### 10.1 테스트 범위
- [ ] HasVideoField Trait 테스트
- [ ] 업로드 프로세스 테스트
- [ ] 인코딩 Job 테스트
- [ ] API 엔드포인트 테스트
- [ ] 오키드 스크린 테스트

### 10.2 성능 테스트
- [ ] 대용량 파일 업로드
- [ ] 동시 인코딩 처리
- [ ] 메모리 누수 확인
- [ ] 데이터베이스 쿼리 최적화

---

## 개발 우선순위

### 🚀 Phase 1-2 (핵심 기능)
1. HasVideoField Trait 구현
2. 기본 데이터 모델 구성
3. 프로파일 시스템

### 📈 Phase 3-5 (핵심 기능)
1. 업로드 시스템
2. FFmpeg 인코딩
3. 썸네일 생성

### 🔧 Phase 6-8 (관리 및 최적화)
1. 오키드 관리자 화면
2. API 시스템
3. 테스트 및 최적화

---

**프로젝트 목표**: 총 8-10주 내 완성  
**최종 업데이트**: 2024년 12월 26일  
**담당자**: xiso (ceo@amuz.co.kr)  
**상태**: 계획 단계