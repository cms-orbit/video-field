<!-- 
    예시 4: 커스텀 액션 버튼이 있는 플레이어
    
    슬롯을 사용하여 커스텀 버튼을 추가할 수 있습니다.
-->
<template>
    <div class="example-container">
        <h2>커스텀 액션 플레이어</h2>
        <p>공유, 좋아요, 재생목록 추가 등의 커스텀 버튼을 포함합니다.</p>
        
        <Player 
            :video-id="1"
            :use-native-controls="false"
            :show-quality-selector="true"
            class="w-full aspect-video rounded-lg"
        >
            <template #actions="{ videoData, isPlaying }">
                <!-- 공유 버튼 -->
                <button 
                    @click="handleShare(videoData)"
                    class="control-button"
                    :title="'공유'"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>
                    </svg>
                </button>
                
                <!-- 좋아요 버튼 -->
                <button 
                    @click="handleLike(videoData)"
                    class="control-button"
                    :class="{ 'liked': isLiked }"
                    :title="isLiked ? '좋아요 취소' : '좋아요'"
                >
                    <svg viewBox="0 0 24 24" :fill="isLiked ? 'currentColor' : 'none'" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                    <span v-if="likeCount > 0" class="count">{{ likeCount }}</span>
                </button>
                
                <!-- 재생목록 추가 버튼 -->
                <button 
                    @click="handleAddToPlaylist(videoData)"
                    class="control-button"
                    :title="'재생목록에 추가'"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
                
                <!-- 신고 버튼 -->
                <button 
                    @click="handleReport(videoData)"
                    class="control-button"
                    :title="'신고'"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                    </svg>
                </button>
            </template>
        </Player>
        
        <!-- 액션 로그 -->
        <div class="action-log">
            <h3>액션 로그</h3>
            <ul>
                <li v-for="(log, index) in actionLogs" :key="index">
                    {{ log }}
                </li>
                <li v-if="actionLogs.length === 0" class="empty">
                    아직 액션이 없습니다. 위의 버튼을 클릭해보세요!
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Player from '@orbit/video/Player.vue';

const isLiked = ref(false);
const likeCount = ref(42);
const actionLogs = ref([]);

const addLog = (message) => {
    const timestamp = new Date().toLocaleTimeString();
    actionLogs.value.unshift(`[${timestamp}] ${message}`);
    if (actionLogs.value.length > 10) {
        actionLogs.value.pop();
    }
};

const handleShare = (videoData) => {
    addLog(`공유 버튼 클릭: ${videoData.title || '제목 없음'}`);
    
    if (navigator.share) {
        navigator.share({
            title: videoData.title,
            url: window.location.href
        }).then(() => {
            addLog('공유 완료');
        }).catch(() => {
            addLog('공유 취소됨');
        });
    } else {
        navigator.clipboard.writeText(window.location.href);
        addLog('링크가 클립보드에 복사되었습니다');
    }
};

const handleLike = (videoData) => {
    isLiked.value = !isLiked.value;
    likeCount.value += isLiked.value ? 1 : -1;
    addLog(isLiked.value ? '좋아요 추가' : '좋아요 취소');
};

const handleAddToPlaylist = (videoData) => {
    addLog(`재생목록에 추가: ${videoData.title || '제목 없음'}`);
    // 실제 구현: API 호출
};

const handleReport = (videoData) => {
    addLog(`신고: ${videoData.title || '제목 없음'}`);
    // 실제 구현: 신고 모달 표시
};
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

.control-button {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.control-button.liked {
    color: #ef4444;
}

.control-button .count {
    font-size: 0.75rem;
}

.action-log {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.action-log h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.action-log ul {
    list-style: none;
    padding: 0;
    font-family: monospace;
    font-size: 0.875rem;
}

.action-log li {
    padding: 0.5rem;
    background: white;
    margin-bottom: 0.25rem;
    border-radius: 0.25rem;
}

.action-log li.empty {
    color: #9ca3af;
    text-align: center;
}
</style>

