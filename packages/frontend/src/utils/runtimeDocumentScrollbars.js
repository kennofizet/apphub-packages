import runtimeDocumentScrollbarCss from '../modules/desktop/styles/runtime-document-scrollbars.css?raw'

export const RUNTIME_SCROLLBAR_STYLE_ID = 'apphub-runtime-scrollbars'

/**
 * Inject unified Hub scrollbar styles into an iframe (or parent) document.
 * @param {Document|null|undefined} doc
 */
export function injectRuntimeDocumentScrollbars(doc) {
  if (!doc || doc.getElementById(RUNTIME_SCROLLBAR_STYLE_ID)) return

  const style = doc.createElement('style')
  style.id = RUNTIME_SCROLLBAR_STYLE_ID
  style.textContent = runtimeDocumentScrollbarCss

  const head = doc.head ?? doc.getElementsByTagName('head')[0]
  if (head) {
    head.appendChild(style)
    return
  }

  doc.documentElement?.prepend(style)
}

/**
 * @param {HTMLIFrameElement|null|undefined} iframe
 */
export function injectRuntimeDocumentScrollbarsIntoIframe(iframe) {
  if (!iframe) return
  try {
    const doc = iframe.contentDocument
    if (doc) injectRuntimeDocumentScrollbars(doc)
  } catch {
    // Opaque / cross-origin — hosted HTML is styled when served by App Hub backend.
  }
}
