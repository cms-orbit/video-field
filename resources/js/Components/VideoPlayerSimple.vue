<template>
  <div class="simple-video-player">
    <VideoPlayer 
      :video-data="videoData"
      :autoplay="autoplay"
      :width="width"
      :height="height"
      @play="onPlay"
      @pause="onPause"
      @ended="onEnded"
      @error="onError"
    />
  </div>
</template>

<script setup>
import VideoPlayer from './VideoPlayer.vue'

// Props
const props = defineProps({
  video: {
    type: Object,
    required: true
  },
  autoplay: {
    type: Boolean,
    default: false
  },
  width: {
    type: [String, Number],
    default: '100%'
  },
  height: {
    type: [String, Number],
    default: 'auto'
  }
})

// Emits
const emit = defineEmits(['play', 'pause', 'ended', 'error'])

// Computed video data
const videoData = computed(() => {
  // Convert Laravel video model to player data format
  if (props.video.player_metadata) {
    return props.video.player_metadata
  }
  
  // Fallback format for direct video data
  return {
    id: props.video.id,
    title: props.video.title,
    duration: props.video.duration,
    thumbnail: props.video.thumbnail_url,
    hls: props.video.hls_manifest_url,
    dash: props.video.dash_manifest_url,
    profiles: props.video.available_profiles || {},
    sprite: props.video.sprite_metadata || null,
    supportsAbr: props.video.supports_abr || false
  }
})

// Event handlers
const onPlay = () => emit('play', props.video)
const onPause = () => emit('pause', props.video)
const onEnded = () => emit('ended', props.video)
const onError = (error) => emit('error', error, props.video)
</script>

<script>
import { computed } from 'vue'
export default {
  name: 'VideoPlayerSimple'
}
</script>

<style scoped>
.simple-video-player {
  @apply w-full;
}
</style>