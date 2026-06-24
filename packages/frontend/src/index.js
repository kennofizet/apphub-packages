import './modules/desktop/styles/desktop.css'
import './modules/desktop/styles/theme.css'
import './modules/desktop/styles/scrollbars.css'
import './components/confirm/confirm-dialog.css'
import { reactive } from 'vue'
import { createAppHubApi } from './api/index.js'
import { createCoreApi } from './api/coreApi.js'
import { createZoneContextState } from './composables/createZoneContext.js'
import { APPHUB_ZONE_CONTEXT_KEY } from './composables/useAppHubZoneContext.js'
import { createAppStoreState, provideAppStore } from './modules/app-store/index.js'
import { AppHubDesktop } from './modules/desktop/index.js'
import { AppHubRunner } from './modules/runner/index.js'
import { getAppHubStore, registerAppHubStore, APPHUB_MODULE_STORE_KEY } from './moduleStore.js'
import {
  createWindowManagerState,
  provideWindowManager,
} from './modules/window-manager/index.js'
import {
  evaluateOriginSafety,
  parseDevUserFromBootstrap,
  parseOriginsFromBootstrap,
} from './utils/originSafety.js'
import { loadDevFriendlyOriginsPreference } from './utils/devOriginSettings.js'
import {
  bootstrapResponseFromCache,
  loadBootstrapCache,
  saveBootstrapCache,
} from './utils/bootstrapCache.js'

const bootstrapInflight = new WeakMap()

function buildPublicOptions(options = {}) {
  const origins = Array.isArray(options.allowedRuntimeOrigins)
    ? options.allowedRuntimeOrigins.filter((o) => typeof o === 'string')
    : []

  return {
    language: options.language || 'vi',
    theme: options.theme ?? 'auto',
    themeToggle: options.themeToggle,
    openAppStoreOnMount: options.openAppStoreOnMount !== false,
    /** Optional enterprise host cap for iframe entry_url. Per-app allowlist = catalog entry_url + DEV approval. */
    allowedRuntimeOrigins: origins,
    enterpriseRuntimeOrigins: origins,
    coreUrl: options.coreUrl || '',
    backendUrl: (options.backendUrl || '').replace(/\/$/, ''),
    hasToken: !!(options.token),
    allowSameOriginEmbed: options.allowSameOriginEmbed === true,
    allowUnsafeOrigin: options.allowUnsafeOrigin === true,
    dedicatedHubHost: options.dedicatedHubHost === true,
    hubOrigin: typeof options.hubOrigin === 'string' ? options.hubOrigin.trim() : '',
    productOrigin: typeof options.productOrigin === 'string' ? options.productOrigin.trim() : '',
    runtimePublicUrl: typeof options.runtimePublicUrl === 'string' ? options.runtimePublicUrl.trim() : '',
    enforceDedicatedHubOrigin: options.enforceDedicatedHubOrigin !== false,
    enforceIsolatedHostedRuntime: options.enforceIsolatedHostedRuntime !== false,
    enforceDevFriendlyOrigins: typeof options.enforceDevFriendlyOrigins === 'boolean'
      ? options.enforceDevFriendlyOrigins
      : true,
    isDevUser: options.isDevUser === true,
    serverHubPublicUrl: typeof options.serverHubPublicUrl === 'string' ? options.serverHubPublicUrl.trim() : '',
    serverFrontendOrigin: typeof options.serverFrontendOrigin === 'string' ? options.serverFrontendOrigin.trim() : '',
    serverRuntimePublicUrl: typeof options.serverRuntimePublicUrl === 'string' ? options.serverRuntimePublicUrl.trim() : '',
    serverOriginsAuto: options.serverOriginsAuto === true,
    serverOriginsResolved: options.serverOriginsResolved === true,
    originBootstrapLoading: options.originBootstrapLoading === true,
    originBlocked: false,
    originCheckPending: false,
    originBlockReason: null,
    originBlockParentOrigin: null,
    originBlockExpectedHubOrigin: null,
    originBlockExpectedRuntimeOrigin: null,
  }
}

/** @param {Record<string, unknown>} target */
function applyOriginSafety(target, sourceOptions = {}) {
  const check = evaluateOriginSafety({ ...target, ...sourceOptions })
  target.originBootstrapLoading = check.loading === true
  target.originCheckPending = check.pending === true
  target.originBlocked = !check.safe && check.loading !== true
  target.originBlockReason = check.reason
  target.originBlockParentOrigin = check.parentOrigin
  target.originBlockExpectedHubOrigin = check.expectedHubOrigin
  target.originBlockExpectedRuntimeOrigin = check.expectedRuntimeOrigin
  return check
}

function parseUserFromBootstrap(resp) {
  const data = resp?.data?.data ?? resp?.data ?? {}
  const user = data.user
  if (!user || user.id == null) return null
  return {
    id: user.id,
    name: user.name ?? String(user.id),
  }
}

function applyBootstrapOrigins(store, bootstrapResponse, { fromCache = false } = {}) {
  const {
    hubPublicUrl,
    frontendOrigin,
    runtimePublicUrl,
    originsAuto,
  } = parseOriginsFromBootstrap(bootstrapResponse)

  store.options.isDevUser = parseDevUserFromBootstrap(bootstrapResponse)
  store.options.serverOriginsAuto = originsAuto

  if (!store.options.isDevUser) {
    store.options.enforceDevFriendlyOrigins = true
  } else {
    store.options.enforceDevFriendlyOrigins = loadDevFriendlyOriginsPreference()
  }

  if (hubPublicUrl) {
    store.options.serverHubPublicUrl = hubPublicUrl
  }
  if (frontendOrigin) {
    store.options.serverFrontendOrigin = frontendOrigin
  }
  if (runtimePublicUrl) {
    store.options.serverRuntimePublicUrl = runtimePublicUrl
    if (!store.options.runtimePublicUrl) store.options.runtimePublicUrl = runtimePublicUrl
  }

  store.options.serverOriginsResolved = true

  if (!fromCache) {
    saveBootstrapCache(store.credentials.backendUrl, bootstrapResponse)
  }

  const user = parseUserFromBootstrap(bootstrapResponse)
  if (user && store.zoneContext?.state) {
    store.zoneContext.state.user.id = user.id
    store.zoneContext.state.user.name = user.name
  }

  // Reconcile while originBootstrapLoading may still be true so enableModuleApi runs
  // after disableModuleServices (wasLoading must be captured before clearing the flag).
  reconcileOriginSafety(store)
  store.options.originBootstrapLoading = false
  applyOriginSafety(store.options)
}

function applyCachedBootstrapIfAny(store) {
  const cached = loadBootstrapCache(store.credentials.backendUrl)
  if (!cached) return false
  applyBootstrapOrigins(store, bootstrapResponseFromCache(cached), { fromCache: true })
  return true
}

async function fetchBootstrapSession(store) {
  let promise = bootstrapInflight.get(store)
  if (promise) return promise

  promise = (async () => {
    syncApi(store)
    if (!store.facade?.bootstrap) return

    const res = await store.facade.bootstrap()
    if (res) applyBootstrapOrigins(store, res)
  })().catch(() => {
    store.options.originBootstrapLoading = false
    if (!store.options.serverOriginsResolved) {
      reconcileOriginSafety(store)
    }
  }).finally(() => {
    bootstrapInflight.delete(store)
  })

  bootstrapInflight.set(store, promise)
  return promise
}

function startBootstrapSession(store) {
  const { backendUrl, token } = store.credentials
  if (!backendUrl || !token) {
    store.options.originBootstrapLoading = false
    reconcileOriginSafety(store)
    return Promise.resolve()
  }

  syncApi(store)
  applyOriginSafety(store.options)

  const hadCache = applyCachedBootstrapIfAny(store)
  if (!hadCache && !store.options.originBlocked) {
    store.options.originBootstrapLoading = true
    store.options.originBlocked = false
    applyOriginSafety(store.options)
    disableModuleServices(store)
  }

  return fetchBootstrapSession(store)
}

function reconcileOriginSafety(store) {
  const wasBlocked = store.options.originBlocked === true
  const wasLoading = store.options.originBootstrapLoading === true
  applyOriginSafety(store.options)

  if (store.options.originBootstrapLoading) {
    disableModuleServices(store)
    return
  }

  if (store.options.originBlocked && !wasBlocked) {
    disableModuleServices(store)
  } else if (!store.options.originBlocked && (wasBlocked || wasLoading)) {
    enableModuleApi(store)
  }
}

function disableModuleServices(store) {
  store.facade.setImpl(null)
  store.coreApi = null
}

/** Window manager + app store — required even when origin is blocked (AppHubDesktop setup). */
function ensureModuleInfrastructure(vueApp, store) {
  ensureZoneContext(vueApp, store)
  ensureModuleState(vueApp, store)
}

function enableModuleApi(store) {
  syncApi(store)
  syncZoneContext(store)
}

function enableModuleServices(vueApp, store) {
  ensureModuleInfrastructure(vueApp, store)
  enableModuleApi(store)
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
    hasImpl() {
      return impl != null
    },
    bootstrap: (...args) => impl?.bootstrap?.(...args),
    apps: (...args) => impl?.apps?.(...args),
    launch: (...args) => impl?.launch?.(...args),
    recordBridgeConsent: (...args) => impl?.recordBridgeConsent?.(...args),
    revokeBridgeConsents: (...args) => impl?.revokeBridgeConsents?.(...args),
    createInstallIntent: (...args) => impl?.createInstallIntent?.(...args),
    ping: (...args) => impl?.ping?.(...args),
    verifyLaunchToken: (...args) => impl?.verifyLaunchToken?.(...args),
    usage: (...args) => impl?.usage?.(...args),
    devApps: (...args) => impl?.devApps?.(...args),
    devInspectBundle: (...args) => impl?.devInspectBundle?.(...args),
    devReadBundleFile: (...args) => impl?.devReadBundleFile?.(...args),
    devDisableApp: (...args) => impl?.devDisableApp?.(...args),
    devSetAppStatus: (...args) => impl?.devSetAppStatus?.(...args),
    devRejectPendingVersion: (...args) => impl?.devRejectPendingVersion?.(...args),
    post: (...args) => impl?.post?.(...args),
    registerApp: (...args) => impl?.registerApp?.(...args),
    appVersions: (...args) => impl?.appVersions?.(...args),
    integrationDocs: (...args) => impl?.integrationDocs?.(...args),
    integrationDocsInternal: (...args) => impl?.integrationDocsInternal?.(...args),
    notifications: (...args) => impl?.notifications?.(...args),
    notificationsSummary: (...args) => impl?.notificationsSummary?.(...args),
    notificationsDismiss: (...args) => impl?.notificationsDismiss?.(...args),
    notificationsReadAll: (...args) => impl?.notificationsReadAll?.(...args),
  }
}

function credentialsUnchanged(prev, next) {
  return prev.backendUrl === next.backendUrl
    && prev.token === next.token
    && prev.coreUrl === next.coreUrl
    && prev.hostAccessSecret === next.hostAccessSecret
}

/** Re-apply installAppHubModule with partial patch — keep credentials and public options not in patch. */
function mergeInstallOptions(store, patch = {}) {
  const creds = {
    coreUrl: 'coreUrl' in patch ? (patch.coreUrl || '') : (store.credentials.coreUrl || ''),
    backendUrl: 'backendUrl' in patch ? (patch.backendUrl || '') : (store.credentials.backendUrl || ''),
    token: 'token' in patch ? (patch.token || '') : (store.credentials.token || ''),
    hostAccessSecret: 'hostAccessSecret' in patch
      ? (patch.hostAccessSecret || '')
      : (store.credentials.hostAccessSecret || ''),
  }

  return {
    language: store.options.language,
    theme: store.options.theme,
    themeToggle: store.options.themeToggle,
    openAppStoreOnMount: store.options.openAppStoreOnMount,
    allowedRuntimeOrigins: store.options.allowedRuntimeOrigins,
    allowSameOriginEmbed: store.options.allowSameOriginEmbed,
    allowUnsafeOrigin: store.options.allowUnsafeOrigin,
    dedicatedHubHost: store.options.dedicatedHubHost,
    hubOrigin: store.options.hubOrigin,
    productOrigin: store.options.productOrigin,
    runtimePublicUrl: store.options.runtimePublicUrl,
    enforceDedicatedHubOrigin: store.options.enforceDedicatedHubOrigin,
    enforceIsolatedHostedRuntime: store.options.enforceIsolatedHostedRuntime,
    enforceDevFriendlyOrigins: store.options.enforceDevFriendlyOrigins,
    isDevUser: store.options.isDevUser,
    ...patch,
    ...creds,
  }
}

function applyModuleOptions(store, options = {}) {
  const prevCredentials = { ...store.credentials }
  const merged = mergeInstallOptions(store, options)
  const nextCredentials = buildCredentials(merged)
  Object.assign(store.credentials, nextCredentials)
  const credsUnchanged = credentialsUnchanged(prevCredentials, nextCredentials)

  const nextPublic = buildPublicOptions(merged)
  Object.assign(store.options, {
    language: nextPublic.language,
    theme: nextPublic.theme,
    themeToggle: nextPublic.themeToggle,
    openAppStoreOnMount: nextPublic.openAppStoreOnMount,
    allowedRuntimeOrigins: nextPublic.allowedRuntimeOrigins,
    enterpriseRuntimeOrigins: nextPublic.enterpriseRuntimeOrigins,
    coreUrl: nextPublic.coreUrl,
    backendUrl: nextPublic.backendUrl,
    hasToken: nextPublic.hasToken,
    allowSameOriginEmbed: nextPublic.allowSameOriginEmbed,
    allowUnsafeOrigin: nextPublic.allowUnsafeOrigin,
    dedicatedHubHost: nextPublic.dedicatedHubHost,
    hubOrigin: nextPublic.hubOrigin,
    productOrigin: nextPublic.productOrigin,
    runtimePublicUrl: nextPublic.runtimePublicUrl,
    enforceDedicatedHubOrigin: nextPublic.enforceDedicatedHubOrigin,
    enforceIsolatedHostedRuntime: nextPublic.enforceIsolatedHostedRuntime,
    enforceDevFriendlyOrigins: nextPublic.enforceDevFriendlyOrigins,
  })
  applyOriginSafety(store.options, merged)
  if (store.credentials.backendUrl && store.credentials.token) {
    if (credsUnchanged) {
      reconcileOriginSafety(store)
      if (!store.facade.hasImpl()) {
        enableModuleApi(store)
      }
    } else {
      void startBootstrapSession(store)
    }
  } else {
    reconcileOriginSafety(store)
  }
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
    {
      ensureBootstrapSession: () => fetchBootstrapSession(store),
    },
  )
  app.provide(APPHUB_ZONE_CONTEXT_KEY, store.zoneContext)
}

function syncZoneContext(store) {
  syncCoreApi(store)
  if (store.zoneContext) {
    store.zoneContext.refresh({ skipBootstrap: true })
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
    ensureModuleInfrastructure(vueApp, store)
    reconcileOriginSafety(store)
    return store.facade
  }

  const facade = createApiFacade()
  const moduleOptions = reactive(buildPublicOptions(options))
  applyOriginSafety(moduleOptions, options)
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
  vueApp.provide('apphubHostApp', vueApp)
  vueApp.provide(APPHUB_MODULE_STORE_KEY, store)
  vueApp.component('AppHubDesktop', AppHubDesktop)
  vueApp.component('AppHubRunner', AppHubRunner)

  ensureModuleInfrastructure(vueApp, store)
  void startBootstrapSession(store)

  return facade
}

/** Whether installAppHubModule has been called for this Vue app instance. */
export function isAppHubModuleInstalled(vueApp) {
  return vueApp != null && getAppHubStore(vueApp) != null
}

export { useAppHubHostApi, useAppHubModuleStore } from './composables/useAppHubHostApi.js'
export { useAppHubZoneContext } from './composables/useAppHubZoneContext.js'
export { createAppHubApi } from './api/index.js'
export { createCoreApi } from './api/coreApi.js'
export { AppHubDesktop } from './modules/desktop/index.js'
export {
  createDesktopNotificationsState,
  useDesktopNotifications,
  AppHubDesktopNotifications,
  parseApiError,
} from './modules/notifications/index.js'
export { AppHubRunner } from './modules/runner/index.js'
export { resolveLang } from './i18n/resolveLang.js'
export { resolveTheme, normalizeTheme, isThemeLocked } from './i18n/resolveTheme.js'
export { t } from './i18n/index.js'
export {
  evaluateOriginSafety,
  isEmbeddedFrame,
  isLocalDevOrigin,
  parseOriginsFromBootstrap,
  resolveEffectiveOrigins,
  ORIGIN_UNSAFE_SAME_ORIGIN_EMBED,
  ORIGIN_UNSAFE_DIRECT_PRODUCT_MOUNT,
  ORIGIN_UNSAFE_NOT_CONFIGURED,
  ORIGIN_UNSAFE_WRONG_ORIGIN,
  ORIGIN_UNSAFE_RUNTIME_NOT_CONFIGURED,
  ORIGIN_UNSAFE_RUNTIME_SAME_ORIGIN,
  resolveRuntimeApiBase,
} from './utils/originSafety.js'

/** True when installAppHubModule blocked due to unsafe same-origin embed. */
export function isAppHubOriginBlocked(vueApp) {
  const store = getAppHubStore(vueApp)
  return store?.options?.originBlocked === true
}
export * from './modules/app-store/index.js'
export * from './modules/window-manager/index.js'
export * from './modules/runner/index.js'

