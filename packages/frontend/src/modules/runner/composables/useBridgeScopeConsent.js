import { reactive } from 'vue'

/**
 * Promise-based bridge scope consent dialog (replaces window.confirm).
 */
export function useBridgeScopeConsent(options = {}) {
  const dialog = reactive({
    open: false,
    scope: '',
  })

  /** @type {((value: boolean) => void) | null} */
  let pendingResolve = null

  function requestScopeConsent(scope) {
    const normalized = String(scope ?? '').trim()
    if (!normalized) return Promise.resolve(false)

    if (typeof options.isPreGranted === 'function' && options.isPreGranted(normalized)) {
      return Promise.resolve(true)
    }

    return new Promise((resolve) => {
      pendingResolve = resolve
      dialog.scope = normalized
      dialog.open = true
    })
  }

  function close(result) {
    dialog.open = false
    pendingResolve?.(result)
    pendingResolve = null
    dialog.scope = ''
  }

  function accept() {
    const scope = dialog.scope
    options.onAccepted?.(scope)
    close(true)
  }

  function refuse() {
    close(false)
  }

  return {
    dialog,
    requestScopeConsent,
    accept,
    refuse,
  }
}
