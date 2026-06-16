/** postMessage channel for App Hub ↔ publisher app (sandboxed iframe). */
export const BRIDGE_CHANNEL = 'apphub:bridge'

export const BRIDGE_EVENT_READY = 'apphub:bridge:ready'
export const BRIDGE_EVENT_CALL = 'apphub:bridge:call'
export const BRIDGE_EVENT_RESULT = 'apphub:bridge:result'
export const BRIDGE_EVENT_PING = 'apphub:bridge:ping'

export const BRIDGE_METHODS = new Set([
  'requestPermission',
  'getUserInfo',
  'getProfile',
  'sendDesktopMessage',
  'notify',
  'setTaskbarBadge',
])
