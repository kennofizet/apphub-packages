import { safeParseJson, sanitizeDesktopSession } from '../../../../utils/safeStorage.js'

const SESSION_KEY = 'apphub-desktop-session'

export function loadDesktopSession() {
  try {
    const raw = localStorage.getItem(SESSION_KEY)
    const parsed = safeParseJson(raw, 512 * 1024)
    return parsed ? sanitizeDesktopSession(parsed) : null
  } catch {
    return null
  }
}

export function saveDesktopSession(session) {
  try {
    localStorage.setItem(SESSION_KEY, JSON.stringify(session))
  } catch {
    /* ignore */
  }
}

export function buildDesktopSession(shell, wm, appStore, settings = null) {
  return {
    windows: wm.state.windows.map((win) => ({
      appId: win.id.replace(/^win-/, ''),
      minimized: !!win.minimized,
      display: win.display ?? 'mini',
      x: win.x,
      y: win.y,
      width: win.width,
      height: win.height,
      zIndex: win.zIndex,
    })),
    activeId: wm.state.activeId,
    userApps: shell.state.userApps.map((app) => ({ ...app })),
    installedSlugs: [...(appStore?.state?.installedSlugs ?? [])],
    settings: settings ? { ...settings } : undefined,
  }
}
