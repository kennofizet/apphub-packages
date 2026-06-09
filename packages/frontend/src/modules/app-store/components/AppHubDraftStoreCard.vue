<template>
  <article class="apphub-draft-card">
    <div class="apphub-draft-card__main">
      <div class="apphub-draft-card__icon" aria-hidden="true">{{ app.icon }}</div>
      <div class="apphub-draft-card__body">
        <h3 class="apphub-draft-card__name">{{ app.name }}</h3>
        <p class="apphub-draft-card__slug">{{ app.slug }}</p>
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
      <span v-else class="apphub-draft-card__installed" :title="labels.app_store_installed">
        <span class="apphub-draft-card__installed-check" aria-hidden="true">✓</span>
        {{ labels.app_store_installed }}
      </span>

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
import { computed } from 'vue'

const props = defineProps({
  app: { type: Object, required: true },
  labels: { type: Object, required: true },
  installed: { type: Boolean, default: false },
  canInstall: { type: Boolean, default: true },
  pinging: { type: Boolean, default: false },
  pingResult: { type: Object, default: null },
})

const emit = defineEmits(['install', 'ping'])

const pingLabel = computed(() => {
  if (!props.pingResult) return ''
  if (props.pingResult.ok) {
    const ms = props.pingResult.latency_ms != null ? ` · ${props.pingResult.latency_ms} ms` : ''
    return `${props.labels.draft_ping_ok}${ms}`
  }
  return props.labels.draft_ping_fail
})
</script>
