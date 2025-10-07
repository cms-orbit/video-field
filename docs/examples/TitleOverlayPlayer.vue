<!-- 
    예시 5: 제목/설명 오버레이 플레이어
    
    마우스를 올리면 비디오 정보가 좌상단에 표시됩니다.
-->
<template>
    <div class="example-container">
        <h2>제목/설명 오버레이 플레이어</h2>
        <p>마우스를 비디오 위에 올려보세요. 좌상단에 제목과 설명이 표시됩니다.</p>
        
        <Player 
            :video-id="1"
            :use-native-controls="false"
            :show-quality-selector="true"
            :show-title="true"
            :show-description="true"
            class="w-full aspect-video rounded-lg shadow-lg"
        />
        
        <div class="info-box">
            <h3>주요 기능</h3>
            <ul>
                <li>✓ 마우스 호버 시 제목과 설명 표시</li>
                <li>✓ 부드러운 페이드 인/아웃 애니메이션</li>
                <li>✓ 그라데이션 배경으로 가독성 향상</li>
                <li>✓ 설명은 최대 3줄까지 표시 (말줄임표)</li>
                <li>✓ 독립적으로 제목/설명 활성화 가능</li>
            </ul>
        </div>
        
        <div class="code-box">
            <h3>Props 사용법</h3>
            <pre><code>&lt;Player 
    :video="videoObject"
    :show-title="true"         <span class="comment">&lt;!-- 제목 표시 --&gt;</span>
    :show-description="true"   <span class="comment">&lt;!-- 설명 표시 --&gt;</span>
    :use-native-controls="false"
/&gt;</code></pre>
        </div>
        
        <div class="info-box tip">
            <h3>💡 활용 팁</h3>
            <p>
                <strong>제목만 표시:</strong> <code>:show-title="true"</code>만 설정<br>
                <strong>설명만 표시:</strong> <code>:show-description="true"</code>만 설정<br>
                <strong>둘 다 표시:</strong> 두 props 모두 true로 설정
            </p>
            <p>
                이 기능은 교육 콘텐츠, 프레젠테이션, 튜토리얼 비디오 등에서 
                사용자가 현재 보고 있는 콘텐츠가 무엇인지 쉽게 확인할 수 있도록 도와줍니다.
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

.info-box {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.info-box.tip {
    background: #ecfdf5;
    border-color: #10b981;
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

.info-box p {
    color: #374151;
    margin-bottom: 0.5rem;
    line-height: 1.6;
}

.info-box code {
    background: rgba(0, 0, 0, 0.1);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-family: monospace;
    font-size: 0.875rem;
}

.code-box {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #1f2937;
    border-radius: 0.5rem;
    color: #fff;
}

.code-box h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #fff;
}

.code-box pre {
    margin: 0;
    overflow-x: auto;
}

.code-box code {
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.6;
    color: #e5e7eb;
}

.code-box .comment {
    color: #9ca3af;
    font-style: italic;
}
</style>

