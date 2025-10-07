<!-- 
    예시 3: 자동재생 + 반복 재생 플레이어
    
    배경 비디오나 프로모션 비디오에 적합한 설정입니다.
-->
<template>
    <div class="example-container">
        <h2>자동재생 + 반복재생 플레이어</h2>
        <p>배경 비디오나 프로모션 비디오에 적합합니다.</p>
        
        <div class="video-wrapper">
            <Player 
                :video-id="1"
                :autoplay="true"
                :loop="true"
                :muted="true"
                :use-native-controls="false"
                :show-quality-selector="false"
                :show-download="false"
                class="w-full aspect-video rounded-lg"
            />
            <div class="overlay">
                <h3>프로모션 제목</h3>
                <p>배경에서 자동으로 재생되는 비디오</p>
            </div>
        </div>
        
        <div class="info-box warning">
            <h3>⚠️ 주의사항</h3>
            <p>
                대부분의 브라우저는 음소거되지 않은 자동재생을 차단합니다. 
                자동재생을 원한다면 반드시 <code>muted</code> 옵션을 true로 설정하세요.
            </p>
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

.video-wrapper {
    position: relative;
}

.overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.4);
    color: white;
    pointer-events: none;
    border-radius: 0.5rem;
}

.overlay h3 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.overlay p {
    font-size: 1.125rem;
    color: rgba(255, 255, 255, 0.9);
}

.info-box {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.info-box.warning {
    background: #fef3c7;
    border-color: #fbbf24;
}

.info-box h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.info-box p {
    color: #374151;
    margin-bottom: 0;
}

.info-box code {
    background: rgba(0, 0, 0, 0.1);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-family: monospace;
}
</style>

