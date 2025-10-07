# Video Player 사용 예시

이 디렉토리에는 다양한 상황에서 Video Player를 사용하는 실전 예시가 포함되어 있습니다.

## 예시 목록

### 1. BasicPlayer.vue
**기본 비디오 플레이어**

가장 간단한 형태의 플레이어입니다. 네이티브 브라우저 컨트롤을 사용합니다.

```vue
<Player :video-id="1" :use-native-controls="true" />
```

**사용 케이스:**
- 간단한 비디오 재생
- 빠른 프로토타이핑
- 브라우저 기본 UI 선호

---

### 2. CustomControlsPlayer.vue
**커스텀 컨트롤 플레이어**

화질 선택과 다운로드 기능이 있는 커스텀 컨트롤 플레이어입니다.

```vue
<Player 
    :video-id="1"
    :use-native-controls="false"
    :show-quality-selector="true"
    :show-download="true"
/>
```

**주요 기능:**
- ✓ 재생/일시정지
- ✓ 프로그레스 바 (클릭으로 탐색)
- ✓ 볼륨 컨트롤
- ✓ 화질 선택 (Auto, 1080p, 720p, 480p 등)
- ✓ 다운로드 (Progressive 프로파일)
- ✓ 전체화면

**사용 케이스:**
- 교육 플랫폼
- VOD 서비스
- 프리미엄 콘텐츠

---

### 3. AutoplayLoopPlayer.vue
**자동재생 + 반복재생 플레이어**

배경 비디오나 프로모션 비디오에 적합한 설정입니다.

```vue
<Player 
    :video-id="1"
    :autoplay="true"
    :loop="true"
    :muted="true"
    :use-native-controls="false"
/>
```

**특징:**
- 자동으로 재생 시작
- 끝까지 재생 후 자동 반복
- 음소거 (자동재생을 위해 필수)
- 컨트롤 숨김 가능

**사용 케이스:**
- 랜딩 페이지 배경 비디오
- 프로모션 비디오
- 제품 소개 비디오
- 분위기 있는 배경

---

### 4. CustomActionsPlayer.vue
**커스텀 액션 플레이어**

슬롯을 사용하여 커스텀 버튼을 추가하는 예시입니다.

```vue
<Player :video-id="1">
    <template #actions="{ videoData, isPlaying }">
        <button @click="share(videoData)">공유</button>
        <button @click="like(videoData)">좋아요</button>
        <button @click="addToPlaylist(videoData)">재생목록</button>
    </template>
</Player>
```

**커스텀 액션:**
- 공유 (Web Share API / 클립보드)
- 좋아요 (토글 + 카운트)
- 재생목록 추가
- 신고

**사용 케이스:**
- 소셜 비디오 플랫폼
- 사용자 생성 콘텐츠 (UGC)
- 커뮤니티 기반 플랫폼

---

### 5. TitleOverlayPlayer.vue
**제목/설명 오버레이 플레이어**

마우스 호버 시 비디오 제목과 설명을 표시하는 예시입니다.

```vue
<Player 
    :video-id="1"
    :show-title="true"
    :show-description="true"
    :use-native-controls="false"
/>
```

**특징:**
- 마우스 호버 시 좌상단에 제목/설명 표시
- 부드러운 페이드 인/아웃 애니메이션
- 그라데이션 배경으로 가독성 향상
- 설명은 최대 3줄까지 표시
- 독립적으로 제목/설명 활성화 가능

**사용 케이스:**
- 교육 플랫폼
- 프레젠테이션 비디오
- 튜토리얼 콘텐츠
- 다큐멘터리

---

## 예시 실행 방법

### 개발 서버에서 실행

1. 프로젝트 루트에서 개발 서버 시작:
```bash
yarn dev
# 또는
npm run dev
```

2. 브라우저에서 예시 페이지 열기:
```
http://localhost:5173/video-examples
```

### 예시를 자신의 프로젝트에 통합

1. 예시 파일을 프로젝트로 복사:
```bash
cp packages/cms-orbit-video/docs/examples/CustomControlsPlayer.vue \
   themes/your-theme/Pages/Videos/
```

2. 필요에 맞게 수정:
```vue
<script setup>
// 자신의 데이터 구조에 맞게 조정
const props = defineProps({
    video: Object  // 백엔드에서 전달받은 비디오 객체
});
</script>
```

## 추가 리소스

### 문서
- [사용 가이드](../player-usage.md) - 상세한 Props, 슬롯, 이벤트 설명
- [CHANGELOG](../../CHANGELOG-PLAYER.md) - 변경 사항 및 개선 내역

### 실제 사용 사례
- `themes/jet-stream/Pages/Announcement/Show.vue` - 공지사항 페이지에서의 사용

### API 문서
- Video API 엔드포인트
- 비디오 객체 구조
- 프로파일 및 스트리밍 옵션

## 문제 해결

### 자동재생이 작동하지 않음
```vue
<!-- 해결: muted 속성 추가 -->
<Player :autoplay="true" :muted="true" />
```

### 화질 선택이 표시되지 않음
```vue
<!-- 해결: ABR 스트리밍 확인 + 커스텀 컨트롤 사용 -->
<Player 
    :use-native-controls="false"
    :show-quality-selector="true"
/>
```
- ABR 스트리밍(DASH/HLS)이 준비되어 있어야 합니다.

### 다운로드 버튼이 표시되지 않음
```vue
<!-- 해결: Progressive 프로파일 확인 + 다운로드 활성화 -->
<Player :show-download="true" />
```
- 최소 1개 이상의 Progressive 프로파일이 완료되어 있어야 합니다.

### 커스텀 버튼이 작동하지 않음
```vue
<!-- 올바른 슬롯 사용 -->
<Player>
    <template #actions="{ videoData }">
        <button @click="myAction(videoData)">버튼</button>
    </template>
</Player>
```
- 슬롯 이름은 `#actions`이어야 합니다.
- 커스텀 컨트롤(`useNativeControls: false`)을 사용해야 합니다.

## 피드백

예시가 도움이 되었나요? 개선 사항이나 추가 예시 요청은 이슈로 등록해주세요!

