<template>
  <div class="apphub-store">
    <header class="apphub-store__header">
      <h2 class="apphub-store__title">
        {{ settingsOpen ? labels.settings_title : labels.app_store_title }}
      </h2>
      <div class="apphub-store__toolbar">
        <button
          type="button"
          class="apphub-store__settings-btn"
          :class="{ 'apphub-store__settings-btn--active': settingsOpen }"
          :title="settingsOpen ? labels.settings_close : labels.settings"
          @click="settingsOpen = !settingsOpen"
        >
          <svg v-if="settingsOpen" class="apphub-store__settings-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path
              fill="currentColor"
              d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"
            />
          </svg>
          <svg v-else class="apphub-store__settings-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path
              fill="currentColor"
              d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"
            />
          </svg>
        </button>
      </div>
    </header>

    <div class="apphub-store__content">
      <AppHubAppStoreSettingsPanel
        v-if="settingsOpen"
        :root-app="rootApp"
        @refreshed="reloadCatalog"
      />
      <div v-else ref="scrollRoot" class="apphub-store__panel apphub-store__panel--scroll">
        <input
          v-model="catalog.search"
          type="search"
          class="apphub-store__search"
          :placeholder="labels.app_store_search"
        />

        <p v-if="catalog.loading && !catalog.items.length" class="apphub-store__msg">
          {{ labels.app_store_loading }}
        </p>
        <p v-else-if="catalog.error === 'permission_denied'" class="apphub-store__msg apphub-store__msg--error">
          {{ labels.app_store_permission_denied }}
        </p>
        <p v-else-if="catalog.error === 'load_failed'" class="apphub-store__msg apphub-store__msg--error">
          {{ labels.app_store_load_error }}
        </p>
        <p v-else-if="catalog.error === 'no_api'" class="apphub-store__msg apphub-store__msg--warn">
          {{ labels.app_store_no_api }}
        </p>

        <div
          v-else-if="!catalog.loading && !appStore.filteredStoreApps.length"
          class="apphub-store__empty"
        >
          <p>{{ labels.app_store_empty }}</p>
          <p
            v-if="catalog.loaded && zone?.state?.selectedZoneId && !zone?.state?.viewAllZones"
            class="apphub-store__empty-hint"
          >
            {{ labels.app_store_empty_zone_hint }}
          </p>
        </div>

        <template v-else-if="appStore.filteredStoreApps.length">
          <ul class="apphub-store__grid">
            <li
              v-for="app in appStore.filteredStoreApps"
              :key="app.slug"
              class="apphub-store__card"
              :class="{ 'apphub-store__card--offline': app.status === 'disabled' }"
            >
              <AppHubAppStoreCard
                :app="app"
                :labels="labels"
                :installed="appStore.isInstalled(app.slug)"
                :installed-version="installedVersionFor(app.slug)"
                :can-install="appStore.canInstall(app)"
                @install="onInstall"
                @update="onUpdate"
                @uninstall="onUninstall"
              />
            </li>
          </ul>
          <p v-if="catalog.loadingMore" class="apphub-catalog-footer">{{ labels.app_store_loading_more }}</p>
          <div ref="scrollSentinel" class="apphub-catalog-sentinel" aria-hidden="true" />
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, inject, onMounted, ref, watch } from 'vue'
import {
  getHostApiForApp,
  isBackendReadyForApp,
  resolveRootApp,
} from '../../../composables/useAppHubHostApi.js'
import { useAppHubZoneContext } from '../../../composables/useAppHubZoneContext.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { CATALOG_MODE_STORE } from '../constants/catalogModes.js'
import { useCatalogInfiniteScroll } from '../composables/useCatalogInfiniteScroll.js'
import { useAppStore } from '../composables/useAppStore.js'
import AppHubAppStoreCard from './AppHubAppStoreCard.vue'
import AppHubAppStoreSettingsPanel from './AppHubAppStoreSettingsPanel.vue'

const props = defineProps({
  getInstalledVersion: { type: Function, default: null },
  onInstalled: { type: Function, default: null },
  onUpdateApp: { type: Function, default: null },
  onUninstalled: { type: Function, default: null },
})

const settingsOpen = ref(false)
const appStore = useAppStore()
const catalog = appStore.catalogs.store
const rootApp = resolveRootApp(getCurrentInstance())
const zone = useAppHubZoneContext()
const moduleOptions = inject('apphubOptions', {})
const lang = computed(() => resolveLang(moduleOptions?.language, 'vi'))

const labels = computed(() => ({
  app_store_title: t('app_store_title', lang.value),
  app_store_search: t('app_store_search', lang.value),
  app_store_install: t('app_store_install', lang.value),
  app_store_empty: t('app_store_empty', lang.value),
  app_store_empty_zone_hint: t('app_store_empty_zone_hint', lang.value),
  app_store_loading: t('app_store_loading', lang.value),
  app_store_loading_more: t('app_store_loading_more', lang.value),
  app_store_load_error: t('app_store_load_error', lang.value),
  app_store_permission_denied: t('app_store_permission_denied', lang.value),
  app_store_no_api: t('app_store_no_api', lang.value),
  app_store_unavailable: t('app_store_unavailable', lang.value),
  app_store_status_draft: t('app_store_status_draft', lang.value),
  app_store_status_offline: t('app_store_status_offline', lang.value),
  app_store_installed: t('app_store_installed', lang.value),
  app_store_uninstall: t('app_store_uninstall', lang.value),
  app_store_update: t('app_store_update', lang.value),
  app_store_installed_version: t('app_store_installed_version', lang.value),
  settings: t('app_store_settings_btn', lang.value),
  settings_title: t('app_store_settings_title', lang.value),
  settings_close: t('app_store_settings_close', lang.value),
}))

function hostApiOptions() {
  return {
    backendReady: isBackendReadyForApp(rootApp),
    mode: CATALOG_MODE_STORE,
  }
}

async function reloadCatalog() {
  if (!rootApp) return
  await appStore.loadCatalog(getHostApiForApp(rootApp), hostApiOptions())
}

async function loadMore() {
  if (!rootApp) return
  await appStore.loadMoreCatalog(getHostApiForApp(rootApp), CATALOG_MODE_STORE, hostApiOptions())
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
  () => [zone?.state?.selectedZoneId, zone?.state?.viewAllZones],
  () => {
    if (moduleOptions?.hasToken) reloadCatalog()
  },
)
</script>
