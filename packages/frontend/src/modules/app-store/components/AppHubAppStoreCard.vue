<template>
  <span class="apphub-store__icon">{{ app.icon }}</span>
  <div class="apphub-store__meta">
    <div class="apphub-store__title-row">
      <strong>{{ app.name }}</strong>
      <span
        v-if="statusLabel"
        class="apphub-store__badge"
        :class="statusBadgeClass"
      >
        {{ statusLabel }}
      </span>
    </div>
    <p>{{ app.description }}</p>
  </div>
  <button
    v-if="!installed && canInstall"
    type="button"
    class="apphub-store__btn"
    @click="emit('install', app)"
  >
    {{ labels.app_store_install }}
  </button>
  <span
    v-else-if="!canInstall"
    class="apphub-store__unavailable"
    :title="labels.app_store_unavailable"
  >
    {{ labels.app_store_unavailable }}
  </span>
  <div v-else class="apphub-store__installed-row">
    <span
      v-if="statusLabel"
      class="apphub-store__badge"
      :class="statusBadgeClass"
    >
      {{ statusLabel }}
    </span>
    <span class="apphub-store__installed" :title="labels.app_store_installed">✓</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  app: { type: Object, required: true },
  labels: { type: Object, required: true },
  installed: { type: Boolean, default: false },
  canInstall: { type: Boolean, default: true },
})

const emit = defineEmits(['install'])

const statusLabel = computed(() => {
  if (props.app.status === 'draft') return props.labels.app_store_status_draft
  if (props.app.status === 'disabled') return props.labels.app_store_status_offline
  return ''
})

const statusBadgeClass = computed(() => {
  if (props.app.status === 'draft') return 'apphub-store__badge--draft'
  if (props.app.status === 'disabled') return 'apphub-store__badge--offline'
  return ''
})
</script>
