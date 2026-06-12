<template>
  <div v-if="open" class="apphub-version-history">
    <p class="apphub-version-history__head">{{ labels.title }}</p>
    <p v-if="loading" class="apphub-version-history__msg">{{ labels.loading }}</p>
    <p v-else-if="error" class="apphub-version-history__msg apphub-version-history__msg--error">{{ error }}</p>
    <p v-else-if="!displayRows.length" class="apphub-version-history__msg">{{ labels.empty }}</p>
    <ul v-else class="apphub-version-history__list">
      <li
        v-for="row in displayRows"
        :key="row.version"
        class="apphub-version-history__item"
        :class="{
          'apphub-version-history__item--latest': row.isLatest,
          'apphub-version-history__item--yours': row.isYours,
        }"
      >
        <div class="apphub-version-history__row">
          <strong>v{{ row.version }}</strong>
          <span v-if="row.isLatest" class="apphub-version-history__badge apphub-version-history__badge--latest">
            {{ labels.latest }}
          </span>
          <span v-if="row.isYours" class="apphub-version-history__badge apphub-version-history__badge--yours">
            {{ labels.yours }}
          </span>
        </div>
        <p v-if="row.uploaded_at" class="apphub-version-history__meta">{{ formatDate(row.uploaded_at) }}</p>
        <p v-if="row.bundle_hash" class="apphub-version-history__hash">{{ shortHash(row.bundle_hash) }}</p>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { getHostApiForApp } from '../../../composables/useAppHubHostApi.js'
import { parseApiError } from '../../notifications/utils/parseApiError.js'

const props = defineProps({
  slug: { type: String, required: true },
  rootApp: { type: Object, default: null },
  open: { type: Boolean, default: false },
  labels: { type: Object, required: true },
  /** User's pinned / installed version on this desktop session. */
  installedVersion: { type: String, default: null },
  /** Catalog head version (optional; falls back to API current_version). */
  catalogVersion: { type: String, default: null },
})

const loading = ref(false)
const error = ref('')
const rows = ref([])
const latestFromApi = ref('')

const displayRows = computed(() => {
  const installed = String(props.installedVersion ?? '').trim()
  const latest = String(props.catalogVersion ?? latestFromApi.value ?? '').trim()

  return rows.value.map((row) => ({
    ...row,
    isLatest: latest !== '' && row.version === latest,
    isYours: installed !== '' && row.version === installed,
  }))
})

function shortHash(hash) {
  if (!hash || typeof hash !== 'string') return ''
  return hash.length > 16 ? `${hash.slice(0, 12)}…` : hash
}

function formatDate(value) {
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}

async function load() {
  if (!props.open || !props.slug) return
  const api = getHostApiForApp(props.rootApp)
  if (!api?.appVersions) {
    error.value = props.labels.no_api
    return
  }

  loading.value = true
  error.value = ''
  rows.value = []
  latestFromApi.value = ''

  try {
    const res = await api.appVersions(props.slug)
    latestFromApi.value = res?.data?.data?.current_version ?? ''
    rows.value = res?.data?.data?.versions ?? []
  } catch (err) {
    error.value = parseApiError(err, props.labels.load_error)
  } finally {
    loading.value = false
  }
}

watch(() => [props.open, props.slug], () => load(), { immediate: true })
</script>
