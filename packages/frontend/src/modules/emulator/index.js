import './pc/styles/desktop.css'
import './pc/styles/theme.css'
import './pc/styles/scrollbars.css'
import './mobile/styles/mobile.css'
import './mobile/styles/mobile-control-center.css'
import '../emulator/mobile/styles/mobile-dock.css'
import '../emulator/mobile/styles/phones/iphone/styles.css'
import '../emulator/mobile/styles/phones/android/styles.css'

export { default as AppHubShell } from './components/AppHubShell.vue'
export { default as AppHubDesktop } from './pc/components/AppHubDesktop.vue'
export { default as AppHubMobile } from './mobile/components/AppHubMobile.vue'

export {
  BUILTIN_APP_STORE_ID,
  BUILTIN_DRAFT_STORE_ID,
  BUILTIN_GUIDE_ID,
  PILOT_DRAFT_SLUG,
  getBuiltinDesktopApps,
  getTaskbarBuiltinApps,
} from './pc/data/builtinApps.js'
export { createDesktopShell } from './pc/composables/useDesktopShell.js'
export { default as AppHubGuideApp } from './pc/components/AppHubGuideApp.vue'
export { default as AppHubPlaceholderApp } from './pc/components/AppHubPlaceholderApp.vue'
