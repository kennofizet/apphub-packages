<template>
  <div v-if="apps.length" class="apphub-taskbar-pins" role="toolbar" :aria-label="ariaLabel">
    <button
      v-for="app in apps"
      :key="app.id"
      type="button"
      class="apphub-taskbar-pins__btn"
      :class="skin ? ['apphub-taskbar-pins__btn--skin', `apphub-taskbar-pins__btn--skin-${skin}`] : null"
      :title="app.name"
      @click="emit('open-app', app)"
      @contextmenu.prevent.stop="emit('app-context-menu', app, $event)"
    >
      <AppHubSkinChrome :skin="skin || ''" tone="pin">
        <span class="apphub-taskbar-pins__icon" aria-hidden="true">{{ app.icon }}</span>
      </AppHubSkinChrome>
    </button>
  </div>
</template>

<script setup>
import AppHubSkinChrome from './AppHubSkinChrome.vue'

defineProps({
  apps: { type: Array, default: () => [] },
  ariaLabel: { type: String, default: '' },
  skin: { type: String, default: '' },
})

const emit = defineEmits(['open-app', 'app-context-menu'])
</script>
