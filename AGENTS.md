# CMS-Orbit Video Package 개발 계획서

## 1. 프로젝트 개요

### 1.1 목적
CMS-Orbit을 위한 포괄적인 비디오 업로드 및 멀티 인코딩 시스템 개발

### 1.2 핵심 가치
- **확장성**: 대용량 비디오 파일 처리
- **성능**: 효율적인 인코딩 및 스트리밍
- **사용성**: 직관적인 업로드 인터페이스
- **호환성**: 다양한 디바이스 및 브라우저 지원

## 2. 핵심 기능

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

### 2.3 썸네일 생성
- ✅ 자동 썸네일 추출 (복수개)
- ✅ 사용자 정의 썸네일 업로드
- ✅ 다양한 크기 생성
- ✅ WebP 포맷 지원

### 2.4 스토리지 관리
- ✅ 다중 스토리지 드라이버 (Local, S3, GCS, Azure)
- ✅ CDN 연동
- ✅ 자동 백업 및 복제
- ✅ 스토리지 사용량 모니터링

### 2.5 스트리밍 최적화
- ✅ HLS (HTTP Live Streaming) 지원
- ✅ DASH (Dynamic Adaptive Streaming) 지원
- ✅ 프로그레시브 다운로드
- ✅ 대역폭 기반 품질 자동 조절

## 3. 기술 스택

### 3.1 백엔드
- **PHP 8.3+**: 최신 PHP 기능 활용
- **Laravel 11**: 프레임워크 기반
- **FFmpeg**: 비디오 처리 엔진
- **Redis**: 큐 및 캐싱
- **MySQL/PostgreSQL**: 메타데이터 저장

### 3.2 프론트엔드
- **Vue.js 3**: 반응형 UI 컴포넌트
- **Inertia.js**: SPA 구현
- **TailwindCSS**: 스타일링
- **Video.js**: 비디오 플레이어
- **Dropzone.js**: 파일 업로드

### 3.3 인프라
- **Laravel Horizon**: 큐 모니터링
- **Laravel Telescope**: 디버깅
- **Docker**: 개발 환경 통합

## 4. 아키텍처 설계

### 4.1 시스템 구조
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend UI   │───▶│   API Gateway   │───▶│  Video Service  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │                        │
                                ▼                        ▼
                       ┌─────────────────┐    ┌─────────────────┐
                       │  Upload Queue   │    │ Encoding Queue  │
                       └─────────────────┘    └─────────────────┘
                                │                        │
                                ▼                        ▼
                       ┌─────────────────┐    ┌─────────────────┐
                       │   File Storage  │    │  FFmpeg Worker  │
                       └─────────────────┘    └─────────────────┘
```

### 4.2 데이터 플로우
1. **업로드**: 청크 단위로 파일 업로드
2. **검증**: 파일 무결성 및 포맷 검사
3. **큐잉**: 인코딩 작업 큐에 추가
4. **인코딩**: 백그라운드에서 다중 해상도 변환
5. **저장**: 인코딩된 파일을 스토리지에 저장
6. **알림**: 처리 완료 알림

## 5. 데이터베이스 설계

### 5.1 테이블 구조

#### videos 테이블
```sql
- id (Primary Key)
- uuid (Unique Identifier)
- title (비디오 제목)
- description (설명)
- original_filename (원본 파일명)
- original_size (원본 파일 크기)
- duration (재생 시간)
- mime_type (MIME 타입)
- status (처리 상태)
- user_id (업로더 ID)
- created_at, updated_at
```

#### video_encodings 테이블
```sql
- id (Primary Key)
- video_id (Foreign Key)
- preset (인코딩 프리셋)
- resolution (해상도)
- bitrate (비트레이트)
- file_path (파일 경로)
- file_size (파일 크기)
- status (인코딩 상태)
- created_at, updated_at
```

#### video_thumbnails 테이블
```sql
- id (Primary Key)
- video_id (Foreign Key)
- file_path (썸네일 경로)
- width, height (크기)
- is_primary (대표 썸네일 여부)
- created_at, updated_at
```

#### video_upload_sessions 테이블
```sql
- id (Primary Key)
- session_id (세션 ID)
- filename (파일명)
- total_chunks (전체 청크 수)
- uploaded_chunks (업로드된 청크 수)
- status (업로드 상태)
- created_at, updated_at
```

## 6. API 설계

### 6.1 업로드 API
```
POST /api/video/upload/init          # 업로드 세션 초기화
POST /api/video/upload/chunk         # 청크 업로드
POST /api/video/upload/complete      # 업로드 완료
DELETE /api/video/upload/cancel      # 업로드 취소
```

### 6.2 비디오 관리 API
```
GET /api/videos                      # 비디오 목록
GET /api/videos/{id}                 # 비디오 상세
PUT /api/videos/{id}                 # 비디오 수정
DELETE /api/videos/{id}              # 비디오 삭제
POST /api/videos/{id}/regenerate     # 인코딩 재생성
```

### 6.3 스트리밍 API
```
GET /api/videos/{id}/stream/{quality} # 스트리밍 URL
GET /api/videos/{id}/playlist.m3u8    # HLS 플레이리스트
GET /api/videos/{id}/manifest.mpd     # DASH 매니페스트
```

## 7. 프론트엔드 컴포넌트

### 7.1 Vue 컴포넌트 목록
- `VideoUploader.vue`: 업로드 인터페이스
- `VideoPlayer.vue`: 비디오 플레이어
- `VideoManager.vue`: 비디오 관리 대시보드
- `VideoList.vue`: 비디오 목록
- `EncodingProgress.vue`: 인코딩 진행률
- `ThumbnailSelector.vue`: 썸네일 선택

### 7.2 Orchid 관리자 컴포넌트
- Video 리스트 스크린
- Video 편집 스크린
- 인코딩 상태 모니터링
- 스토리지 사용량 차트

## 8. 큐 시스템 설계

### 8.1 작업 큐 구조
```
video-upload     # 업로드 처리 큐
video-encoding   # 인코딩 처리 큐 (높은 우선순위)
video-thumbnail  # 썸네일 생성 큐
video-cleanup    # 정리 작업 큐 (낮은 우선순위)
```

### 8.2 Job 클래스
- `ProcessVideoUpload`: 업로드된 파일 처리
- `EncodeVideo`: 비디오 인코딩
- `GenerateThumbnails`: 썸네일 생성
- `CleanupTempFiles`: 임시 파일 정리

## 9. 개발 단계별 계획

### Phase 1: 기본 인프라 (1-2주)
- [ ] 패키지 기본 구조 설정
- [ ] ServiceProvider 및 설정 파일
- [ ] 데이터베이스 마이그레이션
- [ ] 기본 모델 클래스

### Phase 2: 파일 업로드 시스템 (2-3주)
- [ ] 청크 업로드 API 구현
- [ ] 파일 검증 시스템
- [ ] 업로드 세션 관리
- [ ] 프론트엔드 업로드 컴포넌트

### Phase 3: 비디오 인코딩 시스템 (3-4주)
- [ ] FFmpeg 래퍼 클래스
- [ ] 인코딩 프리셋 설정
- [ ] 큐 작업 구현
- [ ] 진행률 추적 시스템

### Phase 4: 썸네일 생성 (1-2주)
- [ ] 자동 썸네일 추출
- [ ] 이미지 처리 최적화
- [ ] 썸네일 관리 API

### Phase 5: 스트리밍 최적화 (2-3주)
- [ ] HLS/DASH 스트리밍 구현
- [ ] 적응형 비트레이트 설정
- [ ] CDN 연동

### Phase 6: 관리자 패널 통합 (2주)
- [ ] Orchid 스크린 개발
- [ ] 관리자 대시보드
- [ ] 모니터링 시스템

### Phase 7: 프론트엔드 컴포넌트 (2-3주)
- [ ] 비디오 플레이어 컴포넌트
- [ ] 업로드 UI 개선
- [ ] 반응형 디자인

### Phase 8: 테스트 및 최적화 (2주)
- [ ] 단위 테스트 작성
- [ ] 통합 테스트
- [ ] 성능 최적화
- [ ] 문서화

## 10. 테스트 계획

### 10.1 단위 테스트
- [ ] 모델 테스트
- [ ] 서비스 클래스 테스트
- [ ] API 엔드포인트 테스트
- [ ] 큐 작업 테스트

### 10.2 통합 테스트
- [ ] 전체 업로드 프로세스 테스트
- [ ] 인코딩 파이프라인 테스트
- [ ] 스트리밍 테스트

### 10.3 성능 테스트
- [ ] 대용량 파일 업로드 테스트
- [ ] 동시 인코딩 테스트
- [ ] 메모리 사용량 테스트

## 11. 보안 고려사항

### 11.1 파일 보안
- [ ] 파일 타입 검증
- [ ] 바이러스 스캔 통합
- [ ] 업로드 크기 제한
- [ ] 사용자 권한 검사

### 11.2 스트리밍 보안
- [ ] 토큰 기반 인증
- [ ] 지역 제한 (Geo-blocking)
- [ ] DRM 고려사항

## 12. 모니터링 및 로깅

### 12.1 로깅 시스템
- [ ] 업로드 이벤트 로깅
- [ ] 인코딩 프로세스 로깅
- [ ] 오류 추적 시스템

### 12.2 메트릭 수집
- [ ] 업로드 성공률
- [ ] 인코딩 소요 시간
- [ ] 스토리지 사용량
- [ ] 대역폭 사용량

## 13. 배포 및 운영

### 13.1 배포 전략
- [ ] Docker 컨테이너화
- [ ] CI/CD 파이프라인
- [ ] 스테이징 환경 구축

### 13.2 운영 가이드
- [ ] 설치 가이드 작성
- [ ] 설정 최적화 가이드
- [ ] 트러블슈팅 가이드

## 14. 문서화

### 14.1 개발자 문서
- [ ] API 문서 (OpenAPI/Swagger)
- [ ] 컴포넌트 사용 가이드
- [ ] 확장 가이드

### 14.2 사용자 문서
- [ ] 사용자 매뉴얼
- [ ] FAQ
- [ ] 베스트 프랙티스

---

## 개발 우선순위

### 🚀 높은 우선순위
1. 기본 업로드 시스템
2. 비디오 인코딩
3. 썸네일 생성

### 📈 중간 우선순위
1. 스트리밍 최적화
2. 관리자 패널
3. 프론트엔드 컴포넌트

### 🔧 낮은 우선순위
1. 고급 보안 기능
2. 상세 모니터링
3. 성능 최적화

---

**최종 업데이트**: 2024년 12월 26일  
**담당자**: xiso (ceo@amuz.co.kr)  
**상태**: 계획 단계