import { computed, reactive, watchEffect } from 'vue'
import { resolvePublisherTestVersion } from '../../../../utils/publisherTestVersion.js'
import { resolveAppPermissions } from '../../../../utils/resolveAppPermissions.js'
import { resolveAppApiUrls } from '../../../../utils/resolveAppApiUrls.js'
import { AppHubAppStoreApp } from '../../../app-store/index.js'
import { AppHubDraftStoreApp } from '../../../app-store/index.js'
import { BUILTIN_APP_STORE_ID, getBuiltinDesktopApps, getTaskbarBuiltinApps } from '../data/builtinApps.js'
import AppHubGuideApp from '../components/AppHubGuideApp.vue'
import AppHubSettingsApp from '../components/AppHubSettingsApp.vue'
import AppHubPlaceholderApp from '../components/AppHubPlaceholderApp.vue'
import { AppHubRunner } from '../../../runner/index.js'
import { AppHubDevToolsApp } from '../../../dev-tools/index.js'
import { findAppByName, nextDuplicateName, nextDuplicateSlug } from '../utils/duplicateAppUtils.js'

function isUserRuntimeApp(app) {
  return Boolean(app && !app.builtin && app.slug)
}

function resolveShellLanguage(options) {
  const lang = options.language
  if (lang && typeof lang === 'object' && 'value' in lang) {
    return lang.value ?? 'vi'
  }
  return typeof lang === 'string' ? lang : 'vi'
}

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
    if (app.module === 'dev-tools') return AppHubDevToolsApp
    if (app.module === 'guide') return AppHubGuideApp
    if (app.module === 'settings') return AppHubSettingsApp
    if (isUserRuntimeApp(app)) return AppHubRunner
    return AppHubPlaceholderApp
  }

  const handleInstall = options.handleInstall ?? null
  const handleUninstall = options.handleUninstall ?? null

  function resolveWindowProps(app) {
    if (app.module === 'app-store' || app.module === 'draft-store') {
      return {
        getInstalledVersion: (slug) => findUserAppBySlug(slug)?.installedVersion ?? null,
        onInstalled: async (item) => {
          if (handleInstall) return handleInstall(item, null, 'appstore')
          return onUserAppInstalled(item)
        },
        onUpdateApp: async (item) => {
          if (options.onUpdateApp) return options.onUpdateApp(item)
          return updateInstalledVersion(item?.slug, item?.version)
        },
        onUninstalled: async (item) => {
          if (handleUninstall) return handleUninstall(item)
          if (item?.slug) removeUserApp(`user-${item.slug}`)
        },
      }
    }
    if (app.module === 'dev-tools') {
      return {
        onCatalogChanged: options.onCatalogChanged ?? (() => {}),
      }
    }
    if (isUserRuntimeApp(app)) {
      return {
        slug: app.slug,
        status: app.status ?? 'active',
        installedVersion: app.installedVersion ?? app.version ?? null,
        pending_version: app.pending_version ?? null,
        rejected_version: app.rejected_version ?? null,
        permissions: Array.isArray(app.permissions) ? app.permissions : [],
        apiUrls: resolveAppApiUrls(app),
        entryUrl: app.entry_url ?? null,
        healthcheckUrl: app.healthcheck_url ?? null,
        icon: app.icon ?? '📦',
      icon_url: app.icon_url ?? null,
        language: resolveShellLanguage(options),
        runtimeType: app.runtime_type ?? 'iframe',
      }
    }
    return { title: app.name, icon: app.icon }
  }

  function buildWindowDefinition(app) {
    const runner = isUserRuntimeApp(app)
    const defaultWidth = runner ? 960 : 720
    const defaultHeight = runner ? 600 : 480
    const defaultMiniWidth = runner ? 720 : 720
    const defaultMiniHeight = runner ? 480 : 480

    return {
      id: `win-${app.id}`,
      title: app.windowTitle ?? app.name,
      icon: app.icon,
      icon_url: app.icon_url ?? null,
      component: resolveWindowComponent(app),
      props: resolveWindowProps(app),
      layoutKey: app.layoutKey,
      defaultDisplay: app.defaultDisplay,
      miniWidth: app.miniWidth ?? defaultMiniWidth,
      miniHeight: app.miniHeight ?? defaultMiniHeight,
      width: app.width ?? defaultWidth,
      height: app.height ?? defaultHeight,
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

  function resolveInstalledVersion(app) {
    if (typeof app.installedVersion === 'string' && app.installedVersion.trim()) {
      return app.installedVersion.trim()
    }
    if (typeof app.version === 'string' && app.version.trim()) {
      return app.version.trim()
    }
    return null
  }

  function syncPublisherVersionFields(target, app, method = null) {
    if (!target || !app) return

    if (app.pending_version !== undefined) {
      const pending = typeof app.pending_version === 'string' ? app.pending_version.trim() : ''
      target.pending_version = pending || null
    }

    if (app.rejected_version !== undefined) {
      const rejected = typeof app.rejected_version === 'string' ? app.rejected_version.trim() : ''
      target.rejected_version = rejected || null
    }

    if (app.catalog_version !== undefined) {
      const live = typeof app.catalog_version === 'string' ? app.catalog_version.trim() : ''
      target.catalog_version = live || null
    } else if (app.pending_version && typeof app.version === 'string' && app.version.trim()) {
      const live = app.version.trim()
      const pinned = resolvePublisherTestVersion(app)
      if (pinned && pinned !== live) {
        target.catalog_version = live
      }
    }

    if ((method === 'appstore' || method === 'publish') && app.pending_version) {
      const testVersion = resolvePublisherTestVersion(app)
      if (testVersion) {
        target.installedVersion = testVersion
        target.version = testVersion
      }
    }
  }

  function buildUserApp(app, position, method = null) {
    const status = typeof app.status === 'string' ? app.status : 'active'
    const installedVersion = resolveInstalledVersion(app)
    const entry = {
      id: `user-${app.slug}`,
      slug: app.slug,
      installedVersion,
      version: installedVersion,
      name: app.name,
      icon: app.icon ?? '📦',
      icon_url: app.icon_url ?? null,
      hint: app.description ?? '',
      status,
      runtime_type: typeof app.runtime_type === 'string' ? app.runtime_type : 'iframe',
      entry_url: typeof app.entry_url === 'string' ? app.entry_url : null,
      healthcheck_url: typeof app.healthcheck_url === 'string' ? app.healthcheck_url : null,
      permissions: resolveAppPermissions(app),
      builtin: false,
      local: method === 'local' || app.local === true,
      installMethod: method === 'local' || method === 'appstore' || method === 'publish'
        ? method
        : (app.local ? 'local' : 'appstore'),
      createdAt: app.createdAt ?? new Date().toISOString(),
      windowTitle: app.name,
      width: 960,
      height: 600,
      miniWidth: 720,
      miniHeight: 480,
      desktopX: position?.x ?? null,
      desktopY: position?.y ?? null,
      pending_version: null,
      catalog_version: null,
      rejected_version: null,
    }
    syncPublisherVersionFields(entry, app, method)
    return entry
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
   * @returns {'added'|'replaced'|'updated'|'cancelled'|null}
   */
  function installUserApp(app, position, method = 'local', duplicateChoice = null) {
    if (!app?.slug && !app?.name) return null

    const existingBySlug = app.slug
      ? state.userApps.find((a) => a.slug === app.slug)
      : null

    // Same slug — publish upgrade or catalog refresh. Never show duplicate dialog.
    if (existingBySlug) {
      if (app.status !== undefined) existingBySlug.status = app.status
      if (app.runtime_type !== undefined) existingBySlug.runtime_type = app.runtime_type
      if (method === 'publish') existingBySlug.installMethod = 'publish'
      if (app.entry_url) existingBySlug.entry_url = app.entry_url
      if (app.healthcheck_url) existingBySlug.healthcheck_url = app.healthcheck_url
      if (app.description) existingBySlug.hint = app.description
      const permissions = resolveAppPermissions(app)
      if (permissions.length) existingBySlug.permissions = permissions
      syncPublisherVersionFields(existingBySlug, app, method)
      const nextVersion = method === 'publish'
        ? resolvePublisherTestVersion(app)
        : resolveInstalledVersion(app)
      if (nextVersion && method === 'publish') {
        existingBySlug.installedVersion = nextVersion
        existingBySlug.version = nextVersion
      } else if (nextVersion && !existingBySlug.installedVersion) {
        existingBySlug.installedVersion = nextVersion
        existingBySlug.version = nextVersion
      }
      if (position) {
        existingBySlug.desktopX = position.x
        existingBySlug.desktopY = position.y
      }
      return 'updated'
    }

    const existingByName = findAppByName(app.name, state.userApps)

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

    state.userApps.push(buildUserApp(app, position, method))
    return 'added'
  }

  function patchUserAppBySlug(slug, patch) {
    const normalized = String(slug ?? '').trim()
    if (!normalized || !patch || typeof patch !== 'object') return false
    const app = findUserAppBySlug(normalized)
    if (!app) return false
    if (patch.status !== undefined) app.status = patch.status
    if (patch.runtime_type !== undefined) app.runtime_type = patch.runtime_type
    if (patch.entry_url !== undefined) app.entry_url = patch.entry_url
    if (patch.healthcheck_url !== undefined) app.healthcheck_url = patch.healthcheck_url
    if (patch.name) {
      app.name = patch.name
      app.windowTitle = patch.name
    }
    if (patch.description) app.hint = patch.description
    if (Array.isArray(patch.permissions)) {
      app.permissions = resolveAppPermissions({ permissions: patch.permissions })
    }
    if (patch.pending_version !== undefined) {
      const pending = typeof patch.pending_version === 'string' ? patch.pending_version.trim() : ''
      app.pending_version = pending || null
    }
    if (patch.catalog_version !== undefined) {
      const live = typeof patch.catalog_version === 'string' ? patch.catalog_version.trim() : ''
      app.catalog_version = live || null
    }
    if (patch.rejected_version !== undefined) {
      const rejected = typeof patch.rejected_version === 'string' ? patch.rejected_version.trim() : ''
      app.rejected_version = rejected || null
    }
    return true
  }

  function updateInstalledVersion(slug, version) {
    const normalizedSlug = String(slug ?? '').trim()
    const normalizedVersion = String(version ?? '').trim()
    if (!normalizedSlug || !normalizedVersion) return false

    const app = findUserAppBySlug(normalizedSlug)
    if (!app) return false

    app.installedVersion = normalizedVersion
    app.version = normalizedVersion
    return true
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

  function findUserAppBySlug(slug) {
    const normalized = String(slug ?? '').trim()
    if (!normalized) return null
    return state.userApps.find((a) => a.slug === normalized) ?? null
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
    findUserAppBySlug,
    updateInstalledVersion,
    patchUserAppBySlug,
    renameUserApp,
    removeUserApp,
    tickClock,
  }
}
