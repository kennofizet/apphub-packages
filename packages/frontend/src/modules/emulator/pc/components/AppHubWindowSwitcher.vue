<template>
  <div
    v-if="open"
    class="apphub-window-switcher"
    role="dialog"
    aria-modal="true"
    :aria-label="titleLabel"
    @click.self="emit('cancel')"
    @contextmenu.prevent
  >
    <div class="apphub-window-switcher__panel" @click.stop>
      <p class="apphub-window-switcher__title">{{ titleLabel }}</p>
      <p v-if="!windows.length" class="apphub-window-switcher__empty">{{ emptyLabel }}</p>
      <div v-else class="apphub-window-switcher__grid" role="listbox" :aria-activedescendant="activeDescendantId">
        <button
          v-for="(win, index) in windows"
          :id="`apphub-switcher-item-${win.id}`"
          :key="win.id"
          type="button"
          class="apphub-window-switcher__card"
          :class="{ 'apphub-window-switcher__card--selected': index === selectedIndex }"
          role="option"
          :aria-selected="index === selectedIndex"
          @click="emit('select', win.id)"
          @dblclick="emit('confirm', win.id)"
        >
          <span class="apphub-window-switcher__icon" aria-hidden="true">{{ win.icon || '📦' }}</span>
          <span class="apphub-window-switcher__name" :title="win.title">{{ win.title }}</span>
          <span v-if="win.minimized" class="apphub-window-switcher__badge">{{ minimizedLabel }}</span>
        </button>
      </div>
      <p class="apphub-window-switcher__hint">{{ hintLabel }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  windows: { type: Array, default: () => [] },
  selectedIndex: { type: Number, default: 0 },
  titleLabel: { type: String, default: '' },
  emptyLabel: { type: String, default: '' },
  hintLabel: { type: String, default: '' },
  minimizedLabel: { type: String, default: '' },
})

const emit = defineEmits(['select', 'confirm', 'cancel'])

const activeDescendantId = computed(() => {
  const win = props.windows[props.selectedIndex]
  return win ? `apphub-switcher-item-${win.id}` : undefined
})
</script>
