<template>
  <div
    v-if="open"
    class="apphub-icon-folder"
    :class="{ 'apphub-icon-folder--preview': preview }"
    :style="{ left: `${x}px`, top: `${y}px` }"
    @mousedown.stop
    @click.stop
  >
    <div class="apphub-icon-folder__panel">
      <header class="apphub-icon-folder__header">
        <span class="apphub-icon-folder__title">{{ title }}</span>
        <span class="apphub-icon-folder__count">{{ countLabel }}</span>
      </header>
      <div class="apphub-icon-folder__grid">
        <button
          v-for="app in apps"
          :key="app.id"
          type="button"
          class="apphub-icon-folder__item"
          :class="{
            'apphub-icon-folder__item--dragging': !preview && isDragging(app.id),
            'apphub-icon-folder__item--holding': !preview && isHolding(app.id),
            'apphub-icon-folder__item--preview-new':
              preview && previewNewIds.includes(app.id),
          }"
          :tabindex="preview ? -1 : 0"
          @mousedown.stop="!preview && emit('item-pointer-down', app, $event)"
          @dblclick.stop="!preview && emit('open-app', app)"
          @contextmenu.prevent.stop="!preview && emit('item-context-menu', app, $event)"
        >
          <span class="apphub-icon-folder__item-icon-wrap">
            <span class="apphub-icon-folder__item-icon">{{ app.icon }}</span>
            <span v-if="app.status === 'draft'" class="apphub-icon-folder__item-flag">D</span>
            <span
              v-else-if="showsPendingTest(app)"
              class="apphub-icon-folder__item-flag apphub-icon-folder__item-flag--pending"
            >
              P
            </span>
            <span
              v-else-if="showsRejectedTest(app)"
              class="apphub-icon-folder__item-flag apphub-icon-folder__item-flag--rejected"
            >
              R
            </span>
          </span>
          <span class="apphub-icon-folder__item-label">{{ app.name }}</span>
        </button>
      </div>
      <p class="apphub-icon-folder__hint">{{ hint }}</p>
    </div>
  </div>
</template>

<script setup>
import { isRunningRejectedVersion, isTestingPendingVersion } from '../../../utils/publisherTestVersion.js'

function showsPendingTest(app) {
  return isTestingPendingVersion(app)
}

function showsRejectedTest(app) {
  return isRunningRejectedVersion(app)
}

defineProps({
  open: { type: Boolean, default: false },
  preview: { type: Boolean, default: false },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  apps: { type: Array, default: () => [] },
  title: { type: String, default: '' },
  countLabel: { type: String, default: '' },
  hint: { type: String, default: '' },
  previewNewIds: { type: Array, default: () => [] },
  isDragging: { type: Function, default: () => false },
  isHolding: { type: Function, default: () => false },
})

const emit = defineEmits(['item-pointer-down', 'open-app', 'item-context-menu'])
</script>
