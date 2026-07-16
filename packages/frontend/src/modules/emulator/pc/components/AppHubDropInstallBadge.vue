<template>
  <div
    class="apphub-drop-badge"
    :class="{
      'apphub-drop-badge--installing': job.status === 'installing',
      'apphub-drop-badge--done': job.status === 'done',
      'apphub-drop-badge--error': job.status === 'error',
    }"
    :style="badgeStyle"
  >
    <div class="apphub-drop-badge__icon-wrap">
      <div class="apphub-drop-badge__icon-bg" />
      <span class="apphub-drop-badge__icon">{{ job.icon }}</span>
      <svg v-if="job.status === 'installing'" class="apphub-drop-badge__ring" viewBox="0 0 64 64">
        <circle class="apphub-drop-badge__ring-track" cx="32" cy="32" r="28" />
        <circle
          class="apphub-drop-badge__ring-progress"
          cx="32"
          cy="32"
          r="28"
          :style="{ strokeDashoffset: ringOffset }"
        />
      </svg>
      <span v-if="job.status === 'done'" class="apphub-drop-badge__check">✓</span>
    </div>
    <p class="apphub-drop-badge__label">
      <template v-if="job.status === 'installing'">{{ loadingLabel }}</template>
      <template v-else-if="job.status === 'done'">{{ doneLabel }}</template>
      <template v-else>{{ job.errorMessage || errorLabel }}</template>
    </p>
    <p v-if="job.status === 'installing'" class="apphub-drop-badge__method">{{ methodLabel }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  job: { type: Object, required: true },
  loadingLabel: { type: String, default: '' },
  errorLabel: { type: String, default: '' },
  methodLabel: { type: String, default: '' },
  donePublishLabel: { type: String, default: '' },
})

const doneLabel = computed(() => {
  if (props.job.publishSubmitted && props.job.name) {
    return props.job.name
  }
  if (props.job.publishSubmitted && props.donePublishLabel) {
    return props.donePublishLabel
  }
  return props.job.name
})

const badgeStyle = computed(() => ({
  left: `${props.job.x}px`,
  top: `${props.job.y}px`,
}))

const ringOffset = computed(() => {
  const circumference = 2 * Math.PI * 28
  return circumference - (circumference * props.job.progress) / 100
})
</script>
