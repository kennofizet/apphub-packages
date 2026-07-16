<template>
  <footer
    class="apphub-mobile-dock"
    :class="{
      'apphub-mobile-dock--drop-active': dropActive,
      'apphub-mobile-dock--full': full,
    }"
    @click.stop
  >
    <div class="apphub-mobile-dock__apps" role="toolbar" :aria-label="tasksLabel">
      <button
        v-for="app in apps"
        :key="app.id"
        type="button"
        class="apphub-mobile-dock__app"
        :class="{
          'apphub-mobile-dock__app--dragging': draggingId === app.id,
          'apphub-mobile-dock__app--holding': holdingId === app.id,
        }"
        :title="app.name"
        @pointerdown.stop="emit('app-pointer-down', app, $event)"
        @click="emit('open-app', app)"
      >
        <AppHubCatalogIcon
          :app="app"
          emoji-class="apphub-mobile-dock__app-icon"
          img-class="apphub-mobile-dock__app-icon apphub-mobile-dock__app-icon--image"
        />
      </button>
    </div>
  </footer>
</template>

<script setup>
import AppHubCatalogIcon from '../../../../components/AppHubCatalogIcon.vue'

defineProps({
  apps: { type: Array, default: () => [] },
  tasksLabel: { type: String, default: '' },
  dropActive: { type: Boolean, default: false },
  full: { type: Boolean, default: false },
  draggingId: { type: [String, Number], default: null },
  holdingId: { type: [String, Number], default: null },
})

const emit = defineEmits(['open-app', 'app-pointer-down'])
</script>
