import { onUnmounted, shallowRef, watch } from 'vue'
import { entryUrlOrigin } from '../../../utils/launchUrl.js'
import { createRunnerBridgeHost } from '../bridge/runnerBridgeHost.js'
import { createHostedStorageHost } from '../storage/hostedStorageHost.js'

/**
 * Wire postMessage bridge between Hub runner and sandboxed app iframe.
 */
export function useRunnerBridge(options) {
  const hostRef = shallowRef(null)
  const storageHostRef = shallowRef(null)

  function isOpaqueHostedSandbox() {
    if (typeof options.isOpaqueSandbox === 'function') {
      return options.isOpaqueSandbox()
    }
    return options.isHosted?.() === true
  }

  function sharedHostOptions() {
    return {
      getIframe: () => options.iframeRef?.value ?? null,
      getLaunchContext: () => options.launchContext?.value ?? null,
      appSlug: options.slug,
      getEntryOrigin: () => {
        const url = options.launchUrl?.value ?? options.entryUrl?.() ?? ''
        return entryUrlOrigin(url)
      },
      getDisplayUser: options.getDisplayUser,
      isOpaqueHostedSandbox,
    }
  }

  function mount() {
    hostRef.value?.stop()
    storageHostRef.value?.stop()

    const host = createRunnerBridgeHost({
      getIframe: () => options.iframeRef?.value ?? null,
      getLaunchContext: () => options.launchContext?.value ?? null,
      appSlug: options.slug,
      appName: options.appName,
      bridgeDesktopMessage: options.bridgeDesktopMessage,
      requestScopeConsent: options.requestScopeConsent,
      onSessionScopeGranted: options.onSessionScopeGranted,
      onDesktopMessage: options.onDesktopMessage,
      onNotify: options.onNotify,
      onTaskbarBadge: options.onTaskbarBadge,
      getEntryOrigin: () => {
        const url = options.launchUrl?.value ?? options.entryUrl?.() ?? ''
        return entryUrlOrigin(url)
      },
      getDisplayUser: options.getDisplayUser,
      getBridgeApiBase: options.getBridgeApiBase,
      getPublisherApiBase: options.getPublisherApiBase,
      getManifestPermissions: options.getManifestPermissions,
      getAppVersion: options.getAppVersion,
      getRuntimeType: options.getRuntimeType,
      isOpaqueHostedSandbox,
    })

    host.start()
    hostRef.value = host

    if (isOpaqueHostedSandbox()) {
      const storageHost = createHostedStorageHost(sharedHostOptions())
      storageHost.start()
      storageHostRef.value = storageHost
    }

    return host
  }

  function sendReady() {
    hostRef.value?.sendReady()
    storageHostRef.value?.sendSnapshot?.()
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
    storageHostRef.value?.stop()
    storageHostRef.value = null
  })

  return {
    mount,
    sendReady,
  }
}
