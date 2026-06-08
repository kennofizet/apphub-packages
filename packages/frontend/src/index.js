import './modules/desktop/styles/desktop.css'
import './modules/desktop/styles/theme.css'
import { reactive } from 'vue'
import { createAppHubApi } from './api/index.js'
import { createCoreApi } from './api/coreApi.js'
import { createZoneContextState } from './composables/createZoneContext.js'
import { APPHUB_ZONE_CONTEXT_KEY } from './composables/useAppHubZoneContext.js'
import { createAppStoreState, provideAppStore } from './modules/app-store/index.js'
import { AppHubDesktop } from './modules/desktop/index.js'
import { AppHubRunner } from './modules/runner/index.js'
import { getAppHubStore, registerAppHubStore } from './moduleStore.js'
import {
  createWindowManagerState,
  provideWindowManager,
} from './modules/window-manager/index.js'

function buildPublicOptions(options = {}) {
  const origins = Array.isArray(options.allowedRuntimeOrigins)
    ? options.allowedRuntimeOrigins.filter((o) => typeof o === 'string')
    : []

  return {
    language: options.language || 'vi',
    theme: options.theme ?? 'auto',
    themeToggle: options.themeToggle,
    openAppStoreOnMount: options.openAppStoreOnMount !== false,
    allowedRuntimeOrigins: origins,
    coreUrl: options.coreUrl || '',
    hasToken: !!(options.token),
  }
}

function buildCredentials(options = {}) {
  return {
    coreUrl: options.coreUrl || '',
    backendUrl: options.backendUrl || '',
    token: options.token || '',
    hostAccessSecret: options.hostAccessSecret || '',
  }
}

function createApiFacade() {
  let impl = null
  return {
    setImpl(next) {
      impl = next
    },
    bootstrap: (...args) => impl?.bootstrap?.(...args),
    apps: (...args) => impl?.apps?.(...args),
    launch: (...args) => impl?.launch?.(...args),
    integrationDocs: (...args) => impl?.integrationDocs?.(...args),
    integrationDocsInternal: (...args) => impl?.integrationDocsInternal?.(...args),
    grantBridgeScope: (...args) => impl?.grantBridgeScope?.(...args),
    bridgeUser: (...args) => impl?.bridgeUser?.(...args),
    bridgeDesktopMessage: (...args) => impl?.bridgeDesktopMessage?.(...args),
  }
}

function applyModuleOptions(store, options = {}) {
  const nextPublic = buildPublicOptions({ ...options, token: options.token ?? store.credentials.token })
  Object.assign(store.options, {
    language: nextPublic.language,
    theme: nextPublic.theme,
    themeToggle: nextPublic.themeToggle,
    openAppStoreOnMount: nextPublic.openAppStoreOnMount,
    allowedRuntimeOrigins: nextPublic.allowedRuntimeOrigins,
    coreUrl: nextPublic.coreUrl,
    hasToken: nextPublic.hasToken,
  })
  Object.assign(store.credentials, buildCredentials(options))
}

function syncApi(store) {
  const { credentials, facade, zoneContext } = store
  const api = credentials.backendUrl && credentials.token
    ? createAppHubApi(credentials.backendUrl, credentials.token, {
        hostAccessSecret: credentials.hostAccessSecret,
        getZoneHeaderId: () => zoneContext?.getZoneHeaderId?.() ?? null,
      })
    : null
  facade.setImpl(api)
}

function syncCoreApi(store) {
  const { credentials } = store
  store.coreApi = credentials.coreUrl && credentials.token
    ? createCoreApi(credentials.coreUrl, credentials.token)
    : null
}

function ensureZoneContext(app, store) {
  if (store.zoneContext) return
  store.zoneContext = createZoneContextState(
    () => store.coreApi,
    () => store.facade,
  )
  app.provide(APPHUB_ZONE_CONTEXT_KEY, store.zoneContext)
}

function syncZoneContext(store) {
  syncCoreApi(store)
  if (store.zoneContext) {
    store.zoneContext.refresh()
  }
}

function ensureModuleState(app, store) {
  if (store.windowManager && store.appStore) {
    return
  }

  store.windowManager = createWindowManagerState()
  store.appStore = createAppStoreState()
  provideWindowManager(app, store.windowManager)
  provideAppStore(app, store.appStore)
}

/**
 * Install App Hub — Windows desktop shell + modular apps (App Store default).
 * Returns host API facade — keep in host app code, not publisher apps.
 */
export function installAppHubModule(vueApp, options = {}) {
  let store = getAppHubStore(vueApp)

  if (store) {
    applyModuleOptions(store, options)
    syncApi(store)
    syncZoneContext(store)
    return store.facade
  }

  const facade = createApiFacade()
  const moduleOptions = reactive(buildPublicOptions(options))
  const credentials = buildCredentials(options)

  store = {
    app: vueApp,
    facade,
    options: moduleOptions,
    credentials,
    coreApi: null,
    zoneContext: null,
    windowManager: null,
    appStore: null,
  }
  registerAppHubStore(vueApp, store)

  vueApp.provide('apphubOptions', moduleOptions)
  vueApp.component('AppHubDesktop', AppHubDesktop)
  vueApp.component('AppHubRunner', AppHubRunner)

  ensureZoneContext(vueApp, store)
  ensureModuleState(vueApp, store)
  syncApi(store)
  syncZoneContext(store)

  return facade
}

/** Whether installAppHubModule has been called for this Vue app instance. */
export function isAppHubModuleInstalled(vueApp) {
  return vueApp != null && getAppHubStore(vueApp) != null
}

export { useAppHubHostApi } from './composables/useAppHubHostApi.js'
export { useAppHubZoneContext } from './composables/useAppHubZoneContext.js'
export { createAppHubApi } from './api/index.js'
export { createCoreApi } from './api/coreApi.js'
export { AppHubDesktop } from './modules/desktop/index.js'
export { AppHubRunner } from './modules/runner/index.js'
export { resolveLang } from './i18n/resolveLang.js'
export { resolveTheme, normalizeTheme, isThemeLocked } from './i18n/resolveTheme.js'
export { t } from './i18n/index.js'
export * from './modules/app-store/index.js'
export * from './modules/window-manager/index.js'
export * from './modules/runner/index.js'
