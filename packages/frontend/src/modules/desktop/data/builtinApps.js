/** Built-in desktop apps (always on the Hub desktop). */
export const BUILTIN_APP_STORE_ID = 'builtin-app-store'
export const BUILTIN_GUIDE_ID = 'builtin-guide'
export const BUILTIN_SETTINGS_ID = 'builtin-settings'

export function getBuiltinDesktopApps(labels) {
  return [
    {
      id: BUILTIN_APP_STORE_ID,
      slug: BUILTIN_APP_STORE_ID,
      name: labels.desktop_app_store,
      hint: labels.desktop_app_store_hint,
      icon: '🛒',
      builtin: true,
      windowTitle: labels.app_store_title,
      module: 'app-store',
      layoutKey: BUILTIN_APP_STORE_ID,
      defaultDisplay: 'fullscreen',
      miniWidth: 820,
      miniHeight: 520,
    },
    {
      id: BUILTIN_GUIDE_ID,
      slug: BUILTIN_GUIDE_ID,
      name: labels.guide_app_name,
      hint: labels.guide_app_hint,
      icon: '📖',
      builtin: true,
      windowTitle: labels.guide_app_title,
      module: 'guide',
      layoutKey: BUILTIN_GUIDE_ID,
      defaultDisplay: 'mini',
      miniWidth: 760,
      miniHeight: 560,
    },
    {
      id: BUILTIN_SETTINGS_ID,
      slug: BUILTIN_SETTINGS_ID,
      name: labels.hub_settings_app_name,
      hint: labels.hub_settings_app_hint,
      icon: '⚙️',
      builtin: true,
      windowTitle: labels.hub_settings_app_title,
      module: 'settings',
      layoutKey: BUILTIN_SETTINGS_ID,
      defaultDisplay: 'mini',
      miniWidth: 720,
      miniHeight: 520,
    },
  ]
}
