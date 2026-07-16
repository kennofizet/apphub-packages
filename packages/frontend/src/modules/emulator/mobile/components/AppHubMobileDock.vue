<template>
  <footer class="apphub-mobile-dock" @click.stop>
    <div v-if="windows.length" class="apphub-mobile-dock__apps" role="toolbar" :aria-label="tasksLabel">
      <button
        v-for="win in windows"
        :key="win.id"
        type="button"
        class="apphub-mobile-dock__app"
        :class="{
          'apphub-mobile-dock__app--active': win.id === activeId,
          'apphub-mobile-dock__app--minimized': win.minimized,
        }"
        :title="win.title"
        @click="emit('task-click', win)"
      >
        <span class="apphub-mobile-dock__app-icon" aria-hidden="true">{{ win.icon }}</span>
      </button>
    </div>

    <div class="apphub-mobile-dock__trail">
      <slot name="notifications" />
      <button
        v-if="shutdownAction"
        type="button"
        class="apphub-mobile-dock__shutdown"
        :title="shutdownLabel"
        :aria-label="shutdownLabel"
        @click.stop="emit('shutdown')"
      >
        ⏻
      </button>
    </div>
  </footer>
</template>

<script setup>
defineProps({
  windows: { type: Array, default: () => [] },
  activeId: { type: [String, Number], default: null },
  tasksLabel: { type: String, default: '' },
  shutdownAction: { type: String, default: '' },
  shutdownLabel: { type: String, default: '' },
})

const emit = defineEmits(['task-click', 'shutdown'])
</script>
