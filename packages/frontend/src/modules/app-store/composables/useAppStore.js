import { computed, inject, reactive } from 'vue'
import { defaultAppStoreCatalog } from '../data/defaultCatalog.js'

const APP_STORE_KEY = 'apphubAppStore'

/**
 * Independent App Store module — browse and install user apps into the desktop.
 */
export function createAppStoreState(options = {}) {
  const state = reactive({
    search: '',
    catalog: [...(options.initialCatalog ?? defaultAppStoreCatalog)],
    installedSlugs: options.installedSlugs ?? [],
  })

  const filteredApps = computed(() => {
    const q = state.search.trim().toLowerCase()
    if (!q) return state.catalog
    return state.catalog.filter(
      (app) =>
        app.name.toLowerCase().includes(q) ||
        app.slug.toLowerCase().includes(q) ||
        (app.description || '').toLowerCase().includes(q),
    )
  })

  function isInstalled(slug) {
    return state.installedSlugs.includes(slug)
  }

  function installApp(slug) {
    if (isInstalled(slug)) return
    state.installedSlugs.push(slug)
    const item = state.catalog.find((a) => a.slug === slug)
    if (item) item.installed = true
  }

  function setCatalog(apps) {
    state.catalog = apps
  }

  return {
    state,
    filteredApps,
    isInstalled,
    installApp,
    setCatalog,
  }
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
