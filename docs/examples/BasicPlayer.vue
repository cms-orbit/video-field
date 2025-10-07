<!-- 
    예시 1: 기본 비디오 플레이어
    
    가장 간단한 형태의 비디오 플레이어입니다.
    네이티브 브라우저 컨트롤을 사용합니다.
-->
<template>
    <div class="example-container">
        <h2>기본 비디오 플레이어</h2>
        <p>네이티브 브라우저 컨트롤을 사용한 기본 플레이어입니다.</p>
        
        <Player 
            :video-id="1"
            :use-native-controls="true"
            class="w-full aspect-video rounded-lg"
        />
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
</style>

