import { reactive } from 'vue'

/**
 * Promise-based confirm / alert dialog state for Hub modules.
 */
export function useConfirmDialog() {
  const dialog = reactive({
    open: false,
    title: '',
    message: '',
    hint: '',
    confirmLabel: 'OK',
    cancelLabel: 'Cancel',
    busyLabel: '…',
    danger: false,
    alertOnly: false,
    busy: false,
  })

  let pendingResolve = null

  function close(result) {
    dialog.open = false
    dialog.busy = false
    pendingResolve?.(result)
    pendingResolve = null
  }

  function open(options = {}) {
    return new Promise((resolve) => {
      pendingResolve = resolve
      dialog.title = options.title ?? ''
      dialog.message = options.message ?? ''
      dialog.hint = options.hint ?? ''
      dialog.confirmLabel = options.confirmLabel ?? 'OK'
      dialog.cancelLabel = options.cancelLabel ?? 'Cancel'
      dialog.busyLabel = options.busyLabel ?? '…'
      dialog.danger = options.danger === true
      dialog.alertOnly = options.alertOnly === true
      dialog.busy = false
      dialog.open = true
    })
  }

  function confirm(options) {
    return open({ ...options, alertOnly: false })
  }

  function alert(options) {
    return open({ ...options, alertOnly: true })
  }

  function handleConfirm() {
    if (dialog.alertOnly) {
      close(true)
      return
    }
    close(true)
  }

  function handleCancel() {
    close(false)
  }

  function setBusy(busy) {
    dialog.busy = busy === true
  }

  return {
    dialog,
    confirm,
    alert,
    handleConfirm,
    handleCancel,
    setBusy,
  }
}
