<template>
  <button
    type="button"
    class="apphub-desktop__icon apphub-desktop__icon--placed apphub-desktop__icon--group"
    :class="{
      'apphub-desktop__icon--dragging': dragging,
      'apphub-desktop__icon--holding': holding,
      'apphub-desktop__icon--drop-target': dropHighlight,
    }"
    :style="{ left: `${x}px`, top: `${y}px` }"
    :title="title"
    @pointerdown.stop="emit('pointer-down', $event)"
    @click.stop="emit('click')"
    @contextmenu.prevent.stop="emit('context-menu', $event)"
  >
    <span class="apphub-desktop__group-preview">
      <span
        v-for="(app, i) in previewApps"
        :key="app.id"
        class="apphub-desktop__group-preview-item"
        :style="previewStyle(i)"
      >
        {{ app.icon }}
      </span>
    </span>
    <span class="apphub-desktop__icon-label" :title="label">{{ label }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  apps: { type: Array, default: () => [] },
  x: { type: Number, required: true },
  y: { type: Number, required: true },
  label: { type: String, default: '' },
  title: { type: String, default: '' },
  dragging: { type: Boolean, default: false },
  holding: { type: Boolean, default: false },
  dropHighlight: { type: Boolean, default: false },
})

const emit = defineEmits(['pointer-down', 'click', 'context-menu'])

const previewApps = computed(() => props.apps.slice(0, 4))

const previewOffsets = [
  { left: '4px', top: '4px' },
  { left: '22px', top: '4px' },
  { left: '4px', top: '22px' },
  { left: '22px', top: '22px' },
]

function previewStyle(index) {
  return previewOffsets[index] ?? previewOffsets[0]
}
</script>
