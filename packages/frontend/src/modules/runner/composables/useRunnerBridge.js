import { onUnmounted, shallowRef, watch } from 'vue'
import { entryUrlOrigin } from '../../../utils/launchUrl.js'
import { createRunnerBridgeHost } from '../bridge/runnerBridgeHost.js'

/**
 * Wire postMessage bridge between Hub runner and sandboxed app iframe.
 */
export function useRunnerBridge(options) {
  const hostRef = shallowRef(null)

  function mount() {
    hostRef.value?.stop()

    const host = createRunnerBridgeHost({
      getIframe: () => options.iframeRef?.value ?? null,
      getLaunchContext: () => options.launchContext?.value ?? null,
      appSlug: options.slug,
      appName: options.appName,
      api: options.api,
      requestScopeConsent: options.requestScopeConsent,
      onScopeGranted: options.onScopeGranted,
      onDesktopMessage: options.onDesktopMessage,
      onNotify: options.onNotify,
      onTaskbarBadge: options.onTaskbarBadge,
      getEntryOrigin: () => {
        if (options.isHosted?.()) return ''
        const url = options.launchUrl?.value ?? options.entryUrl?.() ?? ''
        return entryUrlOrigin(url)
      },
    })

    host.start()
    hostRef.value = host
    return host
  }

  function sendReady() {
    hostRef.value?.sendReady()
  }

  watch(
    () => [options.launchContext?.value?.launch_token, options.launchUrl?.value],
    ([token, url]) => {
      if (!token || !url) return
      if (!hostRef.value) mount()
      sendReady()
    },
  )

  onUnmounted(() => {
    hostRef.value?.stop()
    hostRef.value = null
  })

  return {
    mount,
    sendReady,
  }
}
