/**
 * @deprecated Import from `modules/emulator` — kept for backward-compatible paths.
 */
export {
  AppHubShell,
  /** Device-aware shell — registers as AppHubDesktop in installAppHubModule */
  AppHubShell as AppHubDesktop,
  AppHubMobile,
  BUILTIN_APP_STORE_ID,
  BUILTIN_DRAFT_STORE_ID,
  BUILTIN_GUIDE_ID,
  PILOT_DRAFT_SLUG,
  getBuiltinDesktopApps,
  getTaskbarBuiltinApps,
  createDesktopShell,
  AppHubGuideApp,
  AppHubPlaceholderApp,
} from '../emulator/index.js'
