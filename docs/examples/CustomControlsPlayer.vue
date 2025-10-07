<!-- 
    예시 2: 커스텀 컨트롤 비디오 플레이어
    
    화질 선택과 다운로드 기능이 있는 커스텀 컨트롤 플레이어입니다.
-->
<template>
    <div class="example-container">
        <h2>커스텀 컨트롤 플레이어</h2>
        <p>화질 선택, 다운로드 기능이 포함된 커스텀 컨트롤 플레이어입니다.</p>
        
        <Player 
            :video-id="1"
            :use-native-controls="false"
            :show-quality-selector="true"
            :show-download="true"
            :preload="'metadata'"
            class="w-full aspect-video rounded-lg shadow-lg"
        />
        
        <div class="info-box">
            <h3>주요 기능</h3>
            <ul>
                <li>✓ 재생/일시정지</li>
                <li>✓ 프로그레스 바 (클릭으로 탐색)</li>
                <li>✓ 볼륨 컨트롤</li>
                <li>✓ 화질 선택 (Auto, 1080p, 720p, 480p 등)</li>
                <li>✓ 다운로드 (Progressive 프로파일)</li>
                <li>✓ 전체화면</li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import Player from '@orbit/video/Player.vue';
</script>

<style scoped>
.example-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 2rem;
}

h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

p {
    color: #6b7280;
    margin-bottom: 1.5rem;
}

.info-box {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.info-box h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.info-box ul {
    list-style: none;
    padding: 0;
}

.info-box li {
    padding: 0.5rem 0;
    color: #374151;
}
</style>

