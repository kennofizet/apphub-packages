import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { installAppHubModule } from '@kennofizet/apphub-frontend'
import { resolveAppHubStaticConfig } from './apphub-config.js'
import {
  isStandaloneDevSessionEnabled,
  resolveStandaloneDevToken,
} from './standaloneDevSession.js'

/** @see README.md — parent product → Hub iframe */
export const APPHUB_HOST_CHANNEL = 'apphub:host'

function parseThemeToggle(value) {
  if (value === true || value === 'true' || value === '1') return true
  if (value === false || value === 'false' || value === '0') return false
  return undefined
}

function readInitialHostConfig() {
  return {
    token: '',
    language: 'vi',
    theme: 'auto',
    themeToggle: false,
    productOrigin: '',
  }
}

function isAllowedParentOrigin(origin) {
  const allowed = String(import.meta.env.VITE_APPHUB_PARENT_ORIGINS ?? '').trim()
  if (!allowed) return true
  const list = allowed.split(',').map((o) => o.trim()).filter(Boolean)
  return list.includes(origin)
}

function applyHostMessage(config, data) {
  if (!data || typeof data !== 'object') return
  if (data.channel !== APPHUB_HOST_CHANNEL || data.type !== 'config') return

  if (typeof data.token === 'string' && data.token.trim()) {
    config.token = data.token.trim()
  }
  if (typeof data.language === 'string' && data.language.trim()) {
    config.language = data.language.trim()
  }
  if (typeof data.theme === 'string' && data.theme.trim()) {
    config.theme = data.theme.trim()
  }
  const themeToggle = parseThemeToggle(data.themeToggle)
  if (themeToggle !== undefined) {
    config.themeToggle = themeToggle
  }
  if (typeof data.productOrigin === 'string' && data.productOrigin.trim()) {
    config.productOrigin = data.productOrigin.trim()
  }
}

/**
 * Host config from parent iframe (postMessage).
 * Calls installAppHubModule when token + backendUrl are available; re-applies on change.
 */
export function useParentHostConfig(vueApp) {
  const staticConfig = resolveAppHubStaticConfig()
  const config = reactive(readInitialHostConfig())
  const standaloneDevActive = isStandaloneDevSessionEnabled()
  const standaloneDevLoading = ref(false)
  const standaloneDevFailed = ref(false)

  const ready = computed(() => Boolean(staticConfig.backendUrl && config.token))

  const waitingMode = computed(() => {
    if (ready.value) return null
    if (standaloneDevLoading.value) return 'standalone-loading'
    if (standaloneDevActive && standaloneDevFailed.value) return 'standalone-failed'
    if (standaloneDevActive) return 'standalone-idle'
    return 'parent'
  })

  function syncModule() {
    if (!vueApp || !ready.value) return
    installAppHubModule(vueApp, {
      backendUrl: staticConfig.backendUrl,
      coreUrl: staticConfig.coreUrl,
      token: config.token,
      hostAccessSecret: staticConfig.hostAccessSecret || undefined,
      language: config.language,
      theme: config.theme,
      themeToggle: config.themeToggle,
      dedicatedHubHost: true,
      hubOrigin: typeof window !== 'undefined' ? window.location.origin : '',
      productOrigin: config.productOrigin || undefined,
    })
  }

  async function loadStandaloneDevSession() {
    if (!standaloneDevActive || config.token) return

    standaloneDevLoading.value = true
    standaloneDevFailed.value = false
    try {
      const token = await resolveStandaloneDevToken()
      if (token) {
        config.token = token
      } else {
        standaloneDevFailed.value = true
      }
    } catch {
      standaloneDevFailed.value = true
    } finally {
      standaloneDevLoading.value = false
    }
  }

  function onMessage(event) {
    if (!isAllowedParentOrigin(event.origin)) return
    applyHostMessage(config, event.data)
  }

  watch(
    () => [config.token, config.language, config.theme, config.themeToggle, config.productOrigin],
    () => syncModule(),
    { immediate: true },
  )

  onMounted(() => {
    window.addEventListener('message', onMessage)
    if (window.parent !== window) {
      window.parent.postMessage(
        { channel: APPHUB_HOST_CHANNEL, type: 'ready' },
        '*',
      )
    } else {
      void loadStandaloneDevSession()
    }
  })

  onUnmounted(() => {
    window.removeEventListener('message', onMessage)
  })

  return { config, ready, staticConfig, waitingMode }
}
