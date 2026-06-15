<template>
  <div class="apphub-dev-tools__item-head">
    <span aria-hidden="true">{{ app.icon || '📦' }}</span>
    <div class="apphub-dev-tools__meta">
      <strong>{{ app.name }}</strong>
      <span class="apphub-dev-tools__slug">{{ app.slug }}</span>
      <span v-if="app.pending_version" class="apphub-dev-tools__slug">
        · v{{ app.version }} → v{{ app.pending_version }}
      </span>
      <span v-else-if="app.version" class="apphub-dev-tools__slug"> · v{{ app.version }}</span>
      <span class="apphub-dev-tools__badge apphub-dev-tools__badge--draft">
        {{ app.pending_version ? labels.pending_version : labels.status_draft }}
      </span>
      <span class="apphub-dev-tools__badge">{{ runtimeLabel }}</span>
    </div>
    <div class="apphub-dev-tools__actions">
      <button
        v-if="app.runtime_type === 'hosted'"
        type="button"
        class="apphub-dev-tools__btn"
        @click="emit('toggle')"
      >
        {{ labels.view_code }}
      </button>
      <button
        type="button"
        class="apphub-dev-tools__btn apphub-dev-tools__btn--danger"
        :disabled="acting"
        @click="emit('reject')"
      >
        {{ labels.reject }}
      </button>
      <button
        type="button"
        class="apphub-dev-tools__btn apphub-dev-tools__btn--primary"
        :disabled="acting"
        @click="emit('approve')"
      >
        {{ acting ? labels.approving : labels.approve }}
      </button>
    </div>
  </div>

  <dl v-if="app.bundle_hash" class="apphub-dev-tools__dl">
    <div class="apphub-dev-tools__row">
      <dt>{{ labels.hash }}</dt>
      <dd class="apphub-dev-tools__mono">{{ shortHash(app.bundle_hash) }}</dd>
    </div>
    <div v-if="app.bundle_file_count != null" class="apphub-dev-tools__row">
      <dt>{{ labels.file_count }}</dt>
      <dd>{{ app.bundle_file_count }}</dd>
    </div>
  </dl>

  <AppHubDevManifestSummary :app="app" :labels="labels" />
</template>

<script setup>
import { computed } from 'vue'
import AppHubDevManifestSummary from './AppHubDevManifestSummary.vue'

const props = defineProps({
  app: { type: Object, required: true },
  labels: { type: Object, required: true },
  acting: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle', 'approve', 'reject'])

const runtimeLabel = computed(() =>
  props.app?.runtime_type === 'hosted' ? props.labels.hosted : props.labels.iframe,
)

function shortHash(hash) {
  if (!hash || typeof hash !== 'string') return '—'
  return hash.length > 16 ? `${hash.slice(0, 12)}…` : hash
}
</script>
