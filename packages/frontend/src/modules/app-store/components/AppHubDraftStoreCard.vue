<template>
  <article class="apphub-draft-card">
    <div class="apphub-draft-card__main">
      <div class="apphub-draft-card__icon" aria-hidden="true">{{ app.icon }}</div>
      <div class="apphub-draft-card__body">
        <h3 class="apphub-draft-card__name">{{ app.name }}</h3>
        <p class="apphub-draft-card__slug">
          {{ app.slug }}
          <span v-if="app.version"> · v{{ app.version }}</span>
          <span v-if="installedVersion" class="apphub-draft-card__installed-ver">
            · {{ labels.app_store_installed_version }} v{{ installedVersion }}
          </span>
        </p>
        <p v-if="app.description" class="apphub-draft-card__desc">{{ app.description }}</p>
      </div>
    </div>

    <div class="apphub-draft-card__actions">
      <button
        v-if="!installed && canInstall"
        type="button"
        class="apphub-draft-card__btn apphub-draft-card__btn--primary"
        @click="emit('install', app)"
      >
        {{ labels.app_store_install }}
      </button>
      <span
        v-else-if="!canInstall"
        class="apphub-draft-card__unavailable"
      >
        {{ labels.app_store_unavailable }}
      </span>
      <template v-else>
        <span class="apphub-draft-card__installed" :title="labels.app_store_installed">
          <span class="apphub-draft-card__installed-check" aria-hidden="true">✓</span>
          {{ labels.app_store_installed }}
        </span>
        <button
          v-if="updateAvailable"
          type="button"
          class="apphub-draft-card__btn apphub-draft-card__btn--primary"
          @click="emit('update', app)"
        >
          {{ labels.app_store_update }}
        </button>
        <button
          type="button"
          class="apphub-draft-card__btn apphub-draft-card__btn--secondary"
          @click="emit('uninstall', app)"
        >
          {{ labels.app_store_uninstall }}
        </button>
      </template>

      <button
        v-if="app.runtime_type === 'hosted'"
        type="button"
        class="apphub-draft-card__btn apphub-draft-card__btn--secondary"
        @click="historyOpen = !historyOpen"
      >
        {{ labels.dev_review_history_btn }}
      </button>

      <button
        v-if="app.healthcheck_url"
        type="button"
        class="apphub-draft-card__btn apphub-draft-card__btn--secondary"
        :disabled="pinging"
        @click="emit('ping', app)"
      >
        {{ pinging ? labels.draft_ping_pinging : labels.draft_ping_btn }}
      </button>
    </div>

    <AppHubAppVersionHistory
      v-if="app.runtime_type === 'hosted'"
      :slug="app.slug"
      :root-app="rootApp"
      :open="historyOpen"
      :labels="historyLabels"
      :installed-version="installedVersion"
      :catalog-version="app.version"
    />

    <p
      v-if="pingResult"
      class="apphub-draft-card__ping"
      :class="pingResult.ok ? 'apphub-draft-card__ping--ok' : 'apphub-draft-card__ping--bad'"
    >
      {{ pingLabel }}
    </p>
  </article>
</template>

<script setup>
import { computed, ref } from 'vue'
import { isSemverGreaterThan } from '../../../utils/semver.js'
import AppHubAppVersionHistory from './AppHubAppVersionHistory.vue'

const props = defineProps({
  app: { type: Object, required: true },
  labels: { type: Object, required: true },
  rootApp: { type: Object, default: null },
  installed: { type: Boolean, default: false },
  installedVersion: { type: String, default: null },
  canInstall: { type: Boolean, default: true },
  pinging: { type: Boolean, default: false },
  pingResult: { type: Object, default: null },
})

const historyOpen = ref(false)

const updateAvailable = computed(() => {
  if (!props.installed || !props.app?.version) return false
  if (!props.installedVersion) return false
  return isSemverGreaterThan(props.app.version, props.installedVersion)
})

const historyLabels = computed(() => ({
  title: props.labels.dev_review_history_title,
  loading: props.labels.dev_review_history_loading,
  empty: props.labels.dev_review_history_empty,
  latest: props.labels.dev_review_history_latest,
  yours: props.labels.dev_review_history_yours,
  no_api: props.labels.app_store_no_api,
  load_error: props.labels.dev_review_history_error,
}))

const emit = defineEmits(['install', 'uninstall', 'update', 'ping'])

const pingLabel = computed(() => {
  if (!props.pingResult) return ''
  if (props.pingResult.ok) {
    const ms = props.pingResult.latency_ms != null ? ` · ${props.pingResult.latency_ms} ms` : ''
    return `${props.labels.draft_ping_ok}${ms}`
  }
  return props.labels.draft_ping_fail
})
</script>
