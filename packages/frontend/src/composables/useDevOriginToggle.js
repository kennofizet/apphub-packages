import { computed, getCurrentInstance, inject } from 'vue'
import { getAppHubStore } from '../moduleStore.js'
import { saveDevFriendlyOriginsPreference } from '../utils/devOriginSettings.js'
import { isLocalDevHostPage } from '../utils/originSafety.js'

function reconcileAfterToggle(vueApp, enabled) {
  import('../index.js').then(({ installAppHubModule }) => {
    installAppHubModule(vueApp, { enforceDevFriendlyOrigins: enabled, isDevUser: true })
  })
}

/** Dev-only localhost toggle — requires bootstrap is_dev_user (APPHUB_DEV_USER_IDS). */
export function useDevOriginToggle() {
  const moduleOptions = inject('apphubOptions', {})
  const vueApp = getCurrentInstance()?.appContext?.app

  const visible = computed(
    () => moduleOptions?.isDevUser === true && isLocalDevHostPage(),
  )

  const devFriendlyOn = computed(
    () => moduleOptions?.enforceDevFriendlyOrigins !== false,
  )

  function toggle() {
    if (!vueApp || moduleOptions?.isDevUser !== true) return
    const next = !devFriendlyOn.value
    saveDevFriendlyOriginsPreference(next)
    const store = getAppHubStore(vueApp)
    if (store) {
      store.options.enforceDevFriendlyOrigins = next
      if (!next) {
        store.options.hubOrigin = ''
      }
    }
    reconcileAfterToggle(vueApp, next)
  }

  return { visible, devFriendlyOn, toggle }
}
