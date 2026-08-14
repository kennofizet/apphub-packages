<template>
  <button
    type="button"
    class="apphub-start-btn"
    :class="{
      'apphub-start-btn--active': active,
      'apphub-start-btn--skin': Boolean(skin),
      [`apphub-start-btn--skin-${skin}`]: Boolean(skin),
    }"
    :title="title"
    :aria-label="title"
    :aria-expanded="active"
    @click.stop="emit('toggle')"
  >
    <AppHubSkinChrome v-if="skin" :skin="skin" tone="pin">
      <svg class="apphub-start-btn__logo" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M3 3h8.5v8.5H3V3zm9.5 0H21v8.5h-8.5V3zM3 12.5h8.5V21H3v-8.5zm9.5 0H21V21h-8.5v-8.5z" />
      </svg>
    </AppHubSkinChrome>
    <svg
      v-else
      class="apphub-start-btn__logo"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path fill="currentColor" d="M3 3h8.5v8.5H3V3zm9.5 0H21v8.5h-8.5V3zM3 12.5h8.5V21H3v-8.5zm9.5 0H21V21h-8.5v-8.5z" />
    </svg>
  </button>
</template>

<script setup>
import AppHubSkinChrome from './AppHubSkinChrome.vue'

defineProps({
  active: { type: Boolean, default: false },
  title: { type: String, default: 'Start' },
  skin: { type: String, default: '' },
})

const emit = defineEmits(['toggle'])
</script>
