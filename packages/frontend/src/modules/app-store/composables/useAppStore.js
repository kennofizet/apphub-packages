import { computed, inject, reactive } from 'vue'
import { clampPerPage } from '../../../utils/catalogPagination.js'
import { CATALOG_MODE_DRAFT, CATALOG_MODE_PUBLISHER, CATALOG_MODE_STORE } from '../constants/catalogModes.js'
import { normalizeCatalogApp, normalizeCatalogList } from '../utils/normalizeCatalogApp.js'

const APP_STORE_KEY = 'apphubAppStore'

function createCatalogBucket() {
  return {
    items: [],
    search: '',
    loading: false,
    loadingMore: false,
    error: '',
    loaded: false,
    nextCursor: null,
    hasMore: false,
  }
}

function filterItems(items, search) {
  const q = search.trim().toLowerCase()
  if (!q) return items
  return items.filter(
    (app) =>
      app.name.toLowerCase().includes(q) ||
      app.slug.toLowerCase().includes(q) ||
      (app.description || '').toLowerCase().includes(q),
  )
}

function hostApiReady(hostApi) {
  if (!hostApi?.apps) return false
  if (typeof hostApi.hasImpl === 'function') return hostApi.hasImpl()
  return true
}

/**
 * Independent App Store module — separate catalog buckets per mode (store / draft).
 */
export function createAppStoreState(options = {}) {
  const catalogs = reactive({
    store: createCatalogBucket(),
    draft: createCatalogBucket(),
  })

  const state = reactive({
    installedSlugs: options.installedSlugs ?? [],
  })

  const filteredStoreApps = computed(() =>
    filterItems(catalogs.store.items, catalogs.store.search),
  )

  const filteredTestingApps = computed(() =>
    filterItems(catalogs.draft.items, catalogs.draft.search),
  )

  function bucketFor(mode) {
    if (mode === CATALOG_MODE_DRAFT || mode === CATALOG_MODE_PUBLISHER) {
      return catalogs.draft
    }
    return catalogs.store
  }

  function findCatalogItem(slug) {
    return (
      catalogs.store.items.find((a) => a.slug === slug)
      ?? catalogs.draft.items.find((a) => a.slug === slug)
      ?? null
    )
  }

  function isInstalled(slug) {
    return state.installedSlugs.includes(slug)
  }

  function canInstall(app) {
    return app?.status !== 'disabled'
  }

  function installApp(slug) {
    if (isInstalled(slug)) return false
    const item = findCatalogItem(slug)
    if (item && !canInstall(item)) return false
    state.installedSlugs.push(slug)
    if (item) item.installed = true
    return true
  }

  function uninstallApp(slug) {
    const normalized = String(slug ?? '').trim()
    if (!normalized) return false
    const idx = state.installedSlugs.indexOf(normalized)
    if (idx === -1) return false
    state.installedSlugs.splice(idx, 1)
    syncInstalledFlags()
    return true
  }

  function syncInstalledFlags() {
    for (const bucket of Object.values(catalogs)) {
      for (const app of bucket.items) {
        app.installed = state.installedSlugs.includes(app.slug)
      }
    }
  }

  function upsertCatalogItem(mode, row) {
    const item = normalizeCatalogApp(row)
    if (!item) return null
    const bucket = bucketFor(mode)
    const idx = bucket.items.findIndex((a) => a.slug === item.slug)
    if (idx === -1) {
      bucket.items.unshift(item)
    } else {
      const prev = bucket.items[idx]
      bucket.items[idx] = {
        ...prev,
        ...item,
        rejected_version: item.rejected_version ?? prev.rejected_version ?? null,
        awaiting_dev_review: typeof item.awaiting_dev_review === 'boolean'
          ? item.awaiting_dev_review
          : (prev.awaiting_dev_review ?? null),
        current_version_review_status: item.current_version_review_status
          ?? prev.current_version_review_status
          ?? null,
      }
    }
    syncInstalledFlags()
    bucket.loaded = true
    bucket.error = ''
    return item
  }

  async function loadCatalog(hostApi, options = {}) {
    const mode = options.mode === CATALOG_MODE_DRAFT
      ? CATALOG_MODE_DRAFT
      : (options.mode === CATALOG_MODE_PUBLISHER ? CATALOG_MODE_PUBLISHER : CATALOG_MODE_STORE)
    const bucket = bucketFor(mode)
    const append = options.append === true
    const backendReady = options.backendReady !== false

    if (!backendReady || !hostApiReady(hostApi)) {
      if (!append) {
        bucket.items = []
        bucket.error = 'no_api'
        bucket.loaded = false
        bucket.nextCursor = null
        bucket.hasMore = false
      }
      return
    }

    if (append) {
      if (!bucket.hasMore || bucket.loadingMore || bucket.loading) return
      bucket.loadingMore = true
    } else {
      bucket.loading = true
      bucket.error = ''
    }

    try {
      const params = {
        mode,
        per_page: clampPerPage(options.perPage),
      }
      if (append && bucket.nextCursor) {
        params.cursor = bucket.nextCursor
      }

      const res = await hostApi.apps(params)
      if (res === undefined || res === null) {
        if (!append) {
          bucket.items = []
          bucket.error = 'no_api'
          bucket.loaded = false
        }
        return
      }

      const rows = normalizeCatalogList(res?.data?.data ?? res?.data?.datas ?? [])
      const meta = res?.data?.meta ?? {}

      if (append) {
        const existing = new Set(bucket.items.map((a) => a.slug))
        bucket.items.push(...rows.filter((a) => !existing.has(a.slug)))
      } else {
        bucket.items = rows
      }

      bucket.nextCursor = meta.next_cursor ?? null
      bucket.hasMore = meta.has_more === true
      syncInstalledFlags()
      bucket.loaded = true
    } catch (err) {
      if (!append) {
        const status = err?.response?.status
        bucket.items = []
        bucket.error = status === 403 ? 'permission_denied' : 'load_failed'
        bucket.loaded = true
        bucket.nextCursor = null
        bucket.hasMore = false
      }
    } finally {
      if (append) {
        bucket.loadingMore = false
      } else {
        bucket.loading = false
      }
    }
  }

  async function loadMoreCatalog(hostApi, mode, options = {}) {
    const bucket = bucketFor(mode)
    if (!bucket.hasMore || bucket.loadingMore || bucket.loading) return
    await loadCatalog(hostApi, { ...options, mode, append: true })
  }

  return reactive({
    state,
    catalogs,
    filteredStoreApps,
    filteredTestingApps,
    findCatalogItem,
    isInstalled,
    canInstall,
    installApp,
    uninstallApp,
    loadCatalog,
    loadMoreCatalog,
    upsertCatalogItem,
  })
}

export function provideAppStore(app, store) {
  app.provide(APP_STORE_KEY, store)
}

export function useAppStore() {
  const store = inject(APP_STORE_KEY, null)
  if (!store) {
    throw new Error('useAppStore() requires provideAppStore()')
  }
  return store
}
