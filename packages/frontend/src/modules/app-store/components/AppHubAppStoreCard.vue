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
    <p v-if="app.version" class="apphub-store__version">
      v{{ app.version }}
      <span v-if="installedVersion" class="apphub-store__version-installed">
        · {{ labels.app_store_installed_version }} v{{ installedVersion }}
      </span>
    </p>
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
    <span class="apphub-store__installed" :title="labels.app_store_installed">✓</span>
    <button
      v-if="updateAvailable"
      type="button"
      class="apphub-store__btn apphub-store__btn--primary"
      @click="emit('update', app)"
    >
      {{ labels.app_store_update }}
    </button>
    <button
      type="button"
      class="apphub-store__btn apphub-store__btn--secondary"
      @click="emit('uninstall', app)"
    >
      {{ labels.app_store_uninstall }}
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { isSemverGreaterThan } from '../../../utils/semver.js'

const props = defineProps({
  app: { type: Object, required: true },
  labels: { type: Object, required: true },
  installed: { type: Boolean, default: false },
  installedVersion: { type: String, default: null },
  canInstall: { type: Boolean, default: true },
})

const emit = defineEmits(['install', 'uninstall', 'update'])

const updateAvailable = computed(() => {
  if (!props.installed || !props.app?.version) return false
  if (!props.installedVersion) return false
  return isSemverGreaterThan(props.app.version, props.installedVersion)
})

const statusLabel = computed(() => {
  if (props.app.status === 'draft') return props.labels.app_store_status_draft
  if (props.app.status === 'disabled') return props.labels.app_store_status_offline
  if (props.app.health_ok === false) return props.labels.app_store_status_unhealthy
  return ''
})

const statusBadgeClass = computed(() => {
  if (props.app.status === 'draft') return 'apphub-store__badge--draft'
  if (props.app.status === 'disabled') return 'apphub-store__badge--offline'
  if (props.app.health_ok === false) return 'apphub-store__badge--unhealthy'
  return ''
})
</script>
