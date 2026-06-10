<template>
  <section v-if="visible" class="apphub-dev-review">
    <h3 class="apphub-dev-review__title">{{ labels.title }}</h3>
    <p class="apphub-dev-review__hint">{{ labels.hint }}</p>

    <p v-if="loading" class="apphub-dev-review__msg">{{ labels.loading }}</p>
    <p v-else-if="!draftApps.length" class="apphub-dev-review__msg">{{ labels.empty }}</p>

    <ul v-else class="apphub-dev-review__list">
      <li v-for="app in draftApps" :key="app.slug" class="apphub-dev-review__item">
        <div class="apphub-dev-review__item-head">
          <span class="apphub-dev-review__icon" aria-hidden="true">{{ app.icon || '📦' }}</span>
          <div class="apphub-dev-review__meta">
            <strong>{{ app.name }}</strong>
            <span class="apphub-dev-review__slug">{{ app.slug }}</span>
            <span v-if="app.version" class="apphub-dev-review__version">v{{ app.version }}</span>
            <span class="apphub-dev-review__badge">{{ runtimeLabel(app) }}</span>
          </div>
          <div class="apphub-dev-review__actions">
            <button
              type="button"
              class="apphub-dev-review__btn"
              :disabled="historySlug === app.slug"
              @click="toggleHistory(app.slug)"
            >
              {{ historySlug === app.slug ? labels.history_loading : labels.history_btn }}
            </button>
            <button
              v-if="app.runtime_type === 'hosted'"
              type="button"
              class="apphub-dev-review__btn"
              :disabled="inspectingSlug === app.slug"
              @click="toggleInspect(app.slug)"
            >
              {{ inspectingSlug === app.slug ? labels.files_loading : labels.files_btn }}
            </button>
            <button
              type="button"
              class="apphub-dev-review__btn apphub-dev-review__btn--primary"
              :disabled="actingSlug === app.slug"
              @click="approve(app.slug)"
            >
              {{ actingSlug === app.slug ? labels.approving : labels.approve }}
            </button>
          </div>
        </div>

        <dl v-if="app.bundle_hash" class="apphub-dev-review__dl">
          <div class="apphub-dev-review__row">
            <dt>{{ labels.hash }}</dt>
            <dd class="apphub-dev-review__mono">{{ shortHash(app.bundle_hash) }}</dd>
          </div>
          <div v-if="app.bundle_file_count != null" class="apphub-dev-review__row">
            <dt>{{ labels.file_count }}</dt>
            <dd>{{ app.bundle_file_count }}</dd>
          </div>
        </dl>

        <AppHubAppVersionHistory
          :slug="app.slug"
          :root-app="rootApp"
          :open="historySlug === app.slug"
          :labels="historyLabels"
        />

        <div v-if="inspect[app.slug]" class="apphub-dev-review__files">
          <p class="apphub-dev-review__files-head">
            {{ labels.files_title }}
            <span v-if="inspect[app.slug].files_truncated"> ({{ labels.files_truncated }})</span>
          </p>
          <ul class="apphub-dev-review__file-list">
            <li v-for="file in inspect[app.slug].files" :key="file" class="apphub-dev-review__file">
              {{ file }}
            </li>
          </ul>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref } from 'vue'
import { getHostApiForApp } from '../../../composables/useAppHubHostApi.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import AppHubAppVersionHistory from './AppHubAppVersionHistory.vue'

const props = defineProps({
  rootApp: { type: Object, default: null },
  devApps: { type: Array, default: () => [] },
})

const emit = defineEmits(['refreshed'])

const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))
const visible = ref(false)
const loading = ref(false)
const actingSlug = ref('')
const inspectingSlug = ref('')
const historySlug = ref('')
const inspect = reactive({})

const labels = computed(() => ({
  title: t('dev_review_title', lang.value),
  hint: t('dev_review_hint', lang.value),
  loading: t('dev_review_loading', lang.value),
  empty: t('dev_review_empty', lang.value),
  hash: t('dev_review_hash', lang.value),
  file_count: t('dev_review_file_count', lang.value),
  files_btn: t('dev_review_files_btn', lang.value),
  files_loading: t('dev_review_files_loading', lang.value),
  files_title: t('dev_review_files_title', lang.value),
  files_truncated: t('dev_review_files_truncated', lang.value),
  approve: t('dev_review_approve', lang.value),
  approving: t('dev_review_approving', lang.value),
  hosted: t('runner_hosted_badge', lang.value),
  iframe: t('dev_review_iframe', lang.value),
  history_btn: t('dev_review_history_btn', lang.value),
  history_loading: t('dev_review_history_loading', lang.value),
}))

const historyLabels = computed(() => ({
  title: t('dev_review_history_title', lang.value),
  loading: t('dev_review_history_loading', lang.value),
  empty: t('dev_review_history_empty', lang.value),
  current: t('dev_review_history_current', lang.value),
  no_api: t('app_store_no_api', lang.value),
  load_error: t('dev_review_history_error', lang.value),
}))

const draftApps = computed(() =>
  (props.devApps ?? []).filter((app) => app?.status === 'draft'),
)

function runtimeLabel(app) {
  return app?.runtime_type === 'hosted' ? labels.value.hosted : labels.value.iframe
}

function shortHash(hash) {
  if (!hash || typeof hash !== 'string') return '—'
  return hash.length > 16 ? `${hash.slice(0, 12)}…` : hash
}

async function checkDevAccess() {
  const api = getHostApiForApp(props.rootApp)
  if (!api?.bootstrap) {
    visible.value = false
    return
  }

  loading.value = true
  try {
    const boot = await api.bootstrap()
    visible.value = boot?.data?.data?.is_dev_user === true
  } catch {
    visible.value = false
  } finally {
    loading.value = false
  }
}

function toggleHistory(slug) {
  historySlug.value = historySlug.value === slug ? '' : slug
}

async function toggleInspect(slug) {
  if (inspect[slug]) {
    delete inspect[slug]
    inspectingSlug.value = ''
    return
  }

  const api = getHostApiForApp(props.rootApp)
  if (!api?.devInspectBundle) return

  inspectingSlug.value = slug
  try {
    const res = await api.devInspectBundle(slug)
    inspect[slug] = res?.data?.data ?? null
  } catch {
    inspect[slug] = { files: [], file_count: 0 }
  } finally {
    inspectingSlug.value = ''
  }
}

async function approve(slug) {
  const api = getHostApiForApp(props.rootApp)
  if (!api?.devSetAppStatus) return

  actingSlug.value = slug
  try {
    await api.devSetAppStatus(slug, 'active')
    delete inspect[slug]
    emit('refreshed')
  } catch {
    /* ignore */
  } finally {
    actingSlug.value = ''
  }
}

onMounted(checkDevAccess)
</script>
