import { computed, reactive, watchEffect } from 'vue'
import { AppHubAppStoreApp } from '../../app-store/index.js'
import { AppHubDraftStoreApp } from '../../app-store/index.js'
import { BUILTIN_APP_STORE_ID, getBuiltinDesktopApps, getTaskbarBuiltinApps } from '../data/builtinApps.js'
import AppHubGuideApp from '../components/AppHubGuideApp.vue'
import AppHubSettingsApp from '../components/AppHubSettingsApp.vue'
import AppHubPlaceholderApp from '../components/AppHubPlaceholderApp.vue'
import { findAppByName, nextDuplicateName, nextDuplicateSlug } from '../utils/duplicateAppUtils.js'

/**
 * Desktop shell — Windows-style surface, icons, launches windows via window-manager.
 */
export function createDesktopShell(options = {}) {
  const resolveLabels = typeof options.getLabels === 'function'
    ? options.getLabels
    : () => options.labels ?? {}

  const state = reactive({
    userApps: [],
    startOpen: false,
    clock: '',
  })

  const builtinApps = computed(() => getBuiltinDesktopApps(resolveLabels()))
  const taskbarBuiltinApps = computed(() => getTaskbarBuiltinApps(resolveLabels()))

  const desktopIcons = computed(() => {
    const icons = [...(builtinApps.value ?? []), ...state.userApps]
    return icons.filter((app) => app && app.id)
  })

  const iconList = reactive([])
  watchEffect(() => {
    iconList.splice(0, iconList.length, ...desktopIcons.value)
  })

  function allUserAppNames() {
    return state.userApps.map((a) => a.name)
  }

  function resolveWindowComponent(app) {
    if (app.module === 'app-store') return AppHubAppStoreApp
    if (app.module === 'draft-store') return AppHubDraftStoreApp
    if (app.module === 'guide') return AppHubGuideApp
    if (app.module === 'settings') return AppHubSettingsApp
    return AppHubPlaceholderApp
  }

  const handleInstall = options.handleInstall ?? null

  function resolveWindowProps(app) {
    if (app.module === 'app-store' || app.module === 'draft-store') {
      return {
        onInstalled: async (item) => {
          if (handleInstall) return handleInstall(item, null, 'appstore')
          return onUserAppInstalled(item)
        },
      }
    }
    return { title: app.name, icon: app.icon }
  }

  function buildWindowDefinition(app) {
    return {
      id: `win-${app.id}`,
      title: app.windowTitle ?? app.name,
      icon: app.icon,
      component: resolveWindowComponent(app),
      props: resolveWindowProps(app),
      layoutKey: app.layoutKey,
      defaultDisplay: app.defaultDisplay,
      miniWidth: app.miniWidth ?? app.width ?? 720,
      miniHeight: app.miniHeight ?? app.height ?? 480,
      width: app.width ?? app.miniWidth ?? 720,
      height: app.height ?? app.miniHeight ?? 480,
    }
  }

  function findDesktopApp(appId) {
    return desktopIcons.value.find((a) => a.id === appId)
      ?? taskbarBuiltinApps.value.find((a) => a.id === appId)
      ?? null
  }

  function openApp(app, windowManager, sessionState = null) {
    if (app?.id && !sessionState) options.onAppOpened?.(app.id)
    windowManager.openWindow(buildWindowDefinition(app), sessionState)
  }

  function restoreSession(session, windowManager) {
    if (!session) return false

    if (Array.isArray(session.userApps) && session.userApps.length) {
      state.userApps.splice(0, state.userApps.length, ...session.userApps)
    }

    const savedWindows = session.windows ?? []
    for (const saved of savedWindows) {
      const app = findDesktopApp(saved.appId)
      if (app) openApp(app, windowManager, saved)
    }

    windowManager.finishSessionRestore?.(session.activeId)
    return savedWindows.length > 0
  }

  function removeUserApp(appOrId) {
    const id = typeof appOrId === 'string' ? appOrId : appOrId?.id
    const idx = state.userApps.findIndex((a) => a.id === id)
    if (idx !== -1) state.userApps.splice(idx, 1)
  }

  function buildUserApp(app, position, method = null) {
    const status = typeof app.status === 'string' ? app.status : 'active'
    return {
      id: `user-${app.slug}`,
      slug: app.slug,
      name: app.name,
      icon: app.icon ?? '📦',
      hint: app.description ?? '',
      status,
      runtime_type: typeof app.runtime_type === 'string' ? app.runtime_type : 'iframe',
      entry_url: typeof app.entry_url === 'string' ? app.entry_url : null,
      healthcheck_url: typeof app.healthcheck_url === 'string' ? app.healthcheck_url : null,
      builtin: false,
      local: method === 'local' || app.local === true,
      installMethod: method ?? (app.local ? 'local' : 'appstore'),
      createdAt: app.createdAt ?? new Date().toISOString(),
      windowTitle: app.name,
      width: 640,
      height: 420,
      desktopX: position?.x ?? null,
      desktopY: position?.y ?? null,
    }
  }

  function renameUserApp(appId, newName) {
    const app = state.userApps.find((a) => a.id === appId)
    if (!app) return { ok: false, error: 'not_found' }
    const name = String(newName ?? '').trim()
    if (!name) return { ok: false, error: 'empty' }
    const conflict = findAppByName(name, state.userApps)
    if (conflict && conflict.id !== appId) return { ok: false, error: 'duplicate' }
    app.name = name
    app.windowTitle = name
    return { ok: true, app }
  }

  /**
   * Install user app with duplicate handling.
   * @returns {'added'|'replaced'|'cancelled'|null} null if slug already exists without name conflict (app store)
   */
  function installUserApp(app, position, method = 'local', duplicateChoice = null) {
    if (!app?.slug && !app?.name) return null

    const existingByName = findAppByName(app.name, state.userApps)
    const existingBySlug = state.userApps.find((a) => a.slug === app.slug)

    if (existingByName && duplicateChoice === null) {
      return { needsDuplicateChoice: true, existing: existingByName, app, position, method }
    }

    if (duplicateChoice === 'cancel') return 'cancelled'

    if (existingByName && duplicateChoice === 'replace') {
      removeUserApp(existingByName)
      const entry = buildUserApp({ ...app, slug: app.slug ?? existingByName.slug }, position, method)
      state.userApps.push(entry)
      return 'replaced'
    }

    if (existingByName && duplicateChoice === 'keep') {
      const name = nextDuplicateName(app.name, state.userApps)
      const slug = nextDuplicateSlug(app.slug ?? app.name, state.userApps)
      state.userApps.push(buildUserApp({ ...app, name, slug }, position, method))
      return 'added'
    }

    if (existingBySlug && !existingByName) {
      if (app.status) existingBySlug.status = app.status
      if (app.runtime_type) existingBySlug.runtime_type = app.runtime_type
      if (app.entry_url) existingBySlug.entry_url = app.entry_url
      if (app.healthcheck_url) existingBySlug.healthcheck_url = app.healthcheck_url
      if (position) {
        existingBySlug.desktopX = position.x
        existingBySlug.desktopY = position.y
      }
      return null
    }

    state.userApps.push(buildUserApp(app, position, method))
    return 'added'
  }

  function moveUserApp(appId, x, y) {
    const app = state.userApps.find((a) => a.id === appId)
    if (!app) return
    app.desktopX = x
    app.desktopY = y
  }

  function findUserApp(appId) {
    return state.userApps.find((a) => a.id === appId) ?? null
  }

  function onUserAppInstalled(app, position = null) {
    return installUserApp(app, position, 'appstore')
  }

  function addDroppedApp(app, position, method = 'local', duplicateChoice = null) {
    return installUserApp(app, position, method, duplicateChoice)
  }

  function openBuiltinAppStore(windowManager) {
    const app = builtinApps.value.find((a) => a.id === BUILTIN_APP_STORE_ID)
    if (app) openApp(app, windowManager)
  }

  function tickClock() {
    const now = new Date()
    state.clock = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }

  return {
    state,
    desktopIcons,
    taskbarBuiltinApps,
    iconList,
    allUserAppNames,
    openApp,
    openBuiltinAppStore,
    restoreSession,
    findDesktopApp,
    onUserAppInstalled,
    installUserApp,
    addDroppedApp,
    moveUserApp,
    findUserApp,
    renameUserApp,
    removeUserApp,
    tickClock,
  }
}
