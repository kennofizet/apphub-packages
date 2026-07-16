<template>
  <div
    v-if="target"
    class="apphub-desktop__drop-target"
    :style="{ left: `${target.x}px`, top: `${target.y}px` }"
  >
    <div class="apphub-desktop__drop-target-panel">
      <p class="apphub-desktop__drop-target-label">{{ label }}</p>
      <div class="apphub-desktop__drop-target-grid">
        <span
          v-for="app in previewApps"
          :key="app.id"
          class="apphub-desktop__drop-target-icon"
          :title="app.name"
        >
          {{ app.icon }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  target: { type: Object, default: null },
  label: { type: String, default: '' },
})

const previewApps = computed(() => props.target?.apps?.slice(0, 9) ?? [])
</script>
