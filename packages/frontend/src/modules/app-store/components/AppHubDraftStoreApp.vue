<template>
  <div class="apphub-draft-store">
    <header class="apphub-draft-store__hero">
      <span class="apphub-draft-store__hero-icon" aria-hidden="true">🧪</span>
      <div class="apphub-draft-store__hero-text">
        <h2 class="apphub-draft-store__title">{{ labels.draft_store_title }}</h2>
        <p class="apphub-draft-store__intro">{{ labels.draft_store_intro }}</p>
      </div>
    </header>

    <div ref="scrollRoot" class="apphub-draft-store__body apphub-draft-store__body--scroll">
      <input
        v-model="catalog.search"
        type="search"
        class="apphub-draft-store__search"
        :placeholder="labels.app_store_search"
      />

      <p v-if="catalog.loading && !catalog.items.length" class="apphub-draft-store__msg">
        {{ labels.app_store_loading }}
      </p>
      <p v-else-if="catalog.error === 'permission_denied'" class="apphub-draft-store__msg apphub-draft-store__msg--error">
        {{ labels.app_store_permission_denied }}
      </p>
      <p v-else-if="catalog.error === 'load_failed'" class="apphub-draft-store__msg apphub-draft-store__msg--error">
        {{ labels.app_store_load_error }}
      </p>
      <p v-else-if="catalog.error === 'no_api'" class="apphub-draft-store__msg apphub-draft-store__msg--warn">
        {{ labels.app_store_no_api }}
      </p>

      <div
        v-else-if="!catalog.loading && !appStore.filteredTestingApps.length"
        class="apphub-draft-store__empty"
      >
        <span class="apphub-draft-store__empty-icon" aria-hidden="true">📭</span>
        <p>{{ labels.draft_store_empty }}</p>
      </div>

      <template v-else-if="appStore.filteredTestingApps.length">
        <ul class="apphub-draft-store__list">
          <li v-for="app in appStore.filteredTestingApps" :key="app.slug">
            <AppHubDraftStoreCard
              :app="app"
              :labels="labels"
              :root-app="rootApp"
              :installed="appStore.isInstalled(app.slug)"
              :installed-version="installedVersionFor(app.slug)"
              :can-install="appStore.canInstall(app)"
              :pinging="pingingSlug === app.slug"
              :ping-result="pingResults[app.slug] ?? null"
              @install="onInstall"
              @update="onUpdate"
              @uninstall="onUninstall"
              @ping="onPing"
            />
          </li>
        </ul>
        <p v-if="catalog.loadingMore" class="apphub-catalog-footer">{{ labels.app_store_loading_more }}</p>
        <div ref="scrollSentinel" class="apphub-catalog-sentinel" aria-hidden="true" />
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue'
import {
  isBackendReadyFromStore,
  useAppHubHostApi,
  useAppHubModuleStore,
} from '../../../composables/useAppHubHostApi.js'
import { useAppHubZoneContext } from '../../../composables/useAppHubZoneContext.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { CATALOG_MODE_DRAFT } from '../constants/catalogModes.js'
import { useCatalogInfiniteScroll } from '../composables/useCatalogInfiniteScroll.js'
import { useAppStore } from '../composables/useAppStore.js'
import AppHubDraftStoreCard from './AppHubDraftStoreCard.vue'

const props = defineProps({
  getInstalledVersion: { type: Function, default: null },
  onInstalled: { type: Function, default: null },
  onUpdateApp: { type: Function, default: null },
  onUninstalled: { type: Function, default: null },
})

const appStore = useAppStore()
const catalog = appStore.catalogs.draft
const hubStore = useAppHubModuleStore()
const hostApi = useAppHubHostApi()
const rootApp = inject('apphubHostApp', null)
const zone = useAppHubZoneContext()
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))
const pingingSlug = ref('')
const pingResults = reactive({})

const labels = computed(() => ({
  draft_store_title: t('draft_store_title', lang.value),
  draft_store_intro: t('draft_store_intro', lang.value),
  draft_store_empty: t('draft_store_empty', lang.value),
  app_store_search: t('app_store_search', lang.value),
  app_store_install: t('app_store_install', lang.value),
  app_store_loading: t('app_store_loading', lang.value),
  app_store_loading_more: t('app_store_loading_more', lang.value),
  app_store_load_error: t('app_store_load_error', lang.value),
  app_store_permission_denied: t('app_store_permission_denied', lang.value),
  app_store_no_api: t('app_store_no_api', lang.value),
  app_store_unavailable: t('app_store_unavailable', lang.value),
  app_store_status_draft: t('app_store_status_draft', lang.value),
  app_store_installed: t('app_store_installed', lang.value),
  app_store_uninstall: t('app_store_uninstall', lang.value),
  app_store_update: t('app_store_update', lang.value),
  app_store_installed_version: t('app_store_installed_version', lang.value),
  draft_ping_btn: t('draft_ping_btn', lang.value),
  draft_ping_pinging: t('draft_ping_pinging', lang.value),
  draft_ping_ok: t('draft_ping_ok', lang.value),
  draft_ping_fail: t('draft_ping_fail', lang.value),
  dev_review_history_btn: t('dev_review_history_btn', lang.value),
  dev_review_history_loading: t('dev_review_history_loading', lang.value),
  dev_review_history_title: t('dev_review_history_title', lang.value),
  dev_review_history_empty: t('dev_review_history_empty', lang.value),
  dev_review_history_latest: t('dev_review_history_latest', lang.value),
  dev_review_history_yours: t('dev_review_history_yours', lang.value),
  dev_review_history_error: t('dev_review_history_error', lang.value),
}))

function hostApiOptions() {
  return {
    backendReady: isBackendReadyFromStore(hubStore),
    mode: CATALOG_MODE_DRAFT,
  }
}

async function reloadCatalog() {
  await appStore.loadCatalog(hostApi, hostApiOptions())
}

async function loadMore() {
  await appStore.loadMoreCatalog(hostApi, CATALOG_MODE_DRAFT, hostApiOptions())
}

const { rootRef: scrollRoot, sentinelRef: scrollSentinel } = useCatalogInfiniteScroll({
  canLoadMore: () => catalog.hasMore && !catalog.loading && !catalog.loadingMore,
  onLoadMore: loadMore,
})

function installedVersionFor(slug) {
  return props.getInstalledVersion?.(slug) ?? null
}

async function onInstall(app) {
  if (!appStore.installApp(app.slug)) return
  await props.onInstalled?.(app)
}

async function onUpdate(app) {
  await props.onUpdateApp?.(app)
}

async function onUninstall(app) {
  if (!appStore.uninstallApp(app.slug)) return
  await props.onUninstalled?.(app)
}

async function onPing(app) {
  const api = hostApi
  if (!api?.ping || !app?.slug) return
  pingingSlug.value = app.slug
  delete pingResults[app.slug]
  try {
    const res = await api.ping(app.slug)
    pingResults[app.slug] = res?.data?.data ?? { ok: false }
  } catch {
    pingResults[app.slug] = { ok: false }
  } finally {
    pingingSlug.value = ''
  }
}

onMounted(() => {
  if (!catalog.loaded) reloadCatalog()
})

watch(
  () => moduleOptions?.hasToken,
  (hasToken) => {
    if (hasToken && !catalog.loaded) reloadCatalog()
  },
)

watch(
  () => moduleOptions?.originBootstrapLoading,
  (loading, wasLoading) => {
    if (wasLoading && !loading && !moduleOptions?.originBlocked) {
      if (!catalog.loaded || catalog.error === 'no_api') reloadCatalog()
    }
  },
)

watch(
  () => [zone?.state?.selectedZoneId, zone?.state?.viewAllZones],
  () => {
    if (moduleOptions?.hasToken) reloadCatalog()
  },
)
</script>
