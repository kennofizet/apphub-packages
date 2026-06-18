<template>
  <div class="apphub-runner">
    <div v-if="showPreflight" class="apphub-runner__preflight">
      <header class="apphub-runner__preflight-head">
        <span class="apphub-runner__preflight-icon" aria-hidden="true">{{ icon }}</span>
        <div>
          <h3 class="apphub-runner__preflight-title">{{ slug }}</h3>
          <span class="apphub-runner__preflight-badge">{{ labels.draft_badge }}</span>
          <span v-if="isHosted" class="apphub-runner__preflight-badge apphub-runner__preflight-badge--hosted">
            {{ labels.hosted_badge }}
          </span>
        </div>
      </header>

      <dl class="apphub-runner__preflight-dl">
        <div class="apphub-runner__preflight-row">
          <dt>{{ isHosted ? labels.hosted_bundle : labels.entry_url }}</dt>
          <dd>{{ preflightTargetLabel }}</dd>
        </div>
        <div v-if="!isHosted" class="apphub-runner__preflight-row">
          <dt>{{ labels.health_url }}</dt>
          <dd>{{ healthcheckUrl || labels.not_configured }}</dd>
        </div>
      </dl>

      <div class="apphub-runner__preflight-actions">
        <button
          v-if="!isHosted && healthcheckUrl"
          type="button"
          class="apphub-runner__btn apphub-runner__btn--secondary"
          :disabled="pinging"
          @click="onPing"
        >
          {{ pinging ? labels.ping_pinging : labels.ping_btn }}
        </button>
        <button
          v-if="!isHosted && entryUrl"
          type="button"
          class="apphub-runner__btn apphub-runner__btn--secondary"
          @click="onCheckSafe"
        >
          {{ labels.safe_check }}
        </button>
        <button
          type="button"
          class="apphub-runner__btn apphub-runner__btn--primary"
          :disabled="launching"
          @click="onLaunchClick"
        >
          {{ launching ? labels.loading : labels.launch_btn }}
        </button>
      </div>

      <p
        v-if="pingResult"
        class="apphub-runner__preflight-msg"
        :class="pingResult.ok ? 'apphub-runner__preflight-msg--ok' : 'apphub-runner__preflight-msg--bad'"
      >
        {{ pingLabel }}
      </p>
      <p
        v-if="safeResult !== null"
        class="apphub-runner__preflight-msg"
        :class="safeResult ? 'apphub-runner__preflight-msg--ok' : 'apphub-runner__preflight-msg--bad'"
      >
        {{ safeResult ? labels.safe_ok : labels.safe_fail }}
      </p>
      <p v-if="preflightError" class="apphub-runner__preflight-msg apphub-runner__preflight-msg--bad">
        {{ preflightError }}
      </p>
    </div>

    <template v-else>
      <p v-if="loading" class="apphub-runner__msg">{{ labels.loading }}</p>
      <p v-else-if="error" class="apphub-runner__error">{{ error }}</p>
      <iframe
        v-else-if="launchUrl"
        :key="slug"
        ref="iframeRef"
        :src="launchUrl"
        class="apphub-runner__frame"
        :title="slug"
        :sandbox="iframeSandbox"
        referrerpolicy="strict-origin-when-cross-origin"
        @load="onIframeLoad"
      />
    </template>

    <AppHubInstallPermissionsDialog
      :open="scopeConsent.dialog.open"
      theme="dark"
      :title="labels.bridge_perm_title"
      :message="scopeConsentMessage"
      :hint="labels.bridge_perm_runtime_hint"
      :accept-label="labels.install_perm_accept"
      :refuse-label="labels.install_perm_refuse"
      :permission-scopes="scopeConsentScopes"
      :permission-labels="scopeConsentLabels"
      @accept="scopeConsent.accept"
      @refuse="scopeConsent.refuse"
    />
  </div>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue'
import { getAppHubStore } from '../../../moduleStore.js'
import { useAppHubHostApi, useAppHubModuleStore } from '../../../composables/useAppHubHostApi.js'
import { t } from '../../../i18n/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { bridgeScopeLabel } from '../../../utils/appBridgeScopes.js'
import { useDesktopNotifications } from '../../notifications/index.js'
import AppHubInstallPermissionsDialog from '../../desktop/components/AppHubInstallPermissionsDialog.vue'
import {
  RUNTIME_HOSTED,
  iframeSandboxAttrs,
  isAllowedLaunchUrl,
  isEntryUrlAllowed,
  resolveLaunchUrl,
} from '../../../utils/launchUrl.js'
import {
  addInstalledPermission,
  hasInstalledPermission,
} from '../../../utils/installedAppPermissions.js'
import { resolveAppPermissions } from '../../../utils/resolveAppPermissions.js'
import { resolveAppApiUrls } from '../../../utils/resolveAppApiUrls.js'
import { useBridgeScopeConsent } from '../composables/useBridgeScopeConsent.js'
import { injectRuntimeDocumentScrollbarsIntoIframe } from '../../../utils/runtimeDocumentScrollbars.js'
import { useRunnerBridge } from '../composables/useRunnerBridge.js'
import { appendHostedFrameAncestorParams } from '../../../utils/hostedLaunchUrl.js'

const props = defineProps({
  slug: { type: String, required: true },
  installedVersion: { type: String, default: null },
  permissions: { type: Array, default: () => [] },
  apiUrls: { type: Array, default: () => [] },
  status: { type: String, default: 'active' },
  runtimeType: { type: String, default: 'iframe' },
  entryUrl: { type: String, default: null },
  healthcheckUrl: { type: String, default: null },
  language: { type: String, default: 'vi' },
  icon: { type: String, default: '📦' },
})

const api = useAppHubHostApi()
const notifications = useDesktopNotifications()
const moduleOptions = inject('apphubOptions', {})
const allowedOrigins = computed(() => moduleOptions?.allowedRuntimeOrigins ?? [])
const iframeRef = ref(null)
const launchContext = ref(null)
const launchUrl = ref('')

const backendUrl = computed(() => {
  const fromOptions = moduleOptions?.backendUrl
  if (fromOptions) return fromOptions
  return getAppHubStore()?.credentials?.backendUrl ?? ''
})

const lang = computed(() => resolveLang(moduleOptions?.language, props.language))

const isHosted = computed(() => props.runtimeType === RUNTIME_HOSTED || props.runtimeType === 'hosted')

const labels = computed(() => ({
  loading: t('runner_loading', lang.value),
  launch_btn: t('runner_launch_btn', lang.value),
  draft_badge: t('app_store_status_draft', lang.value),
  hosted_badge: t('runner_hosted_badge', lang.value),
  hosted_bundle: t('runner_hosted_bundle', lang.value),
  entry_url: t('draft_ping_entry_url', lang.value),
  health_url: t('draft_ping_health_url', lang.value),
  not_configured: t('draft_ping_no_health_url', lang.value),
  ping_btn: t('draft_ping_btn', lang.value),
  ping_pinging: t('draft_ping_pinging', lang.value),
  ping_ok: t('draft_ping_ok', lang.value),
  ping_fail: t('draft_ping_fail', lang.value),
  safe_check: t('draft_ping_safe_check', lang.value),
  safe_ok: t('draft_ping_safe_ok', lang.value),
  safe_fail: t('draft_ping_safe_fail', lang.value),
  err_no_entry: t('runner_no_entry_url', lang.value),
  err_no_bundle: t('runner_no_bundle', lang.value),
  err_generic: t('error_generic', lang.value),
  bridge_perm_title: t('bridge_perm_title', lang.value),
  bridge_perm_runtime_hint: t('bridge_perm_runtime_hint', lang.value),
  bridge_perm_runtime_message: t('bridge_perm_runtime_message', lang.value),
  install_perm_accept: t('install_perm_accept', lang.value),
  install_perm_refuse: t('install_perm_refuse', lang.value),
}))

function translateBridgeKey(key) {
  return t(key, lang.value)
}

const moduleStore = useAppHubModuleStore()

const manifestPermissions = computed(() => {
  const fromProps = resolveAppPermissions({ permissions: props.permissions })
  if (fromProps.length) return fromProps
  const catalog = moduleStore?.appStore?.findCatalogItem?.(props.slug)
  return resolveAppPermissions(catalog)
})

function onRuntimeScopeGranted(scope) {
  if (!scope) return
  addInstalledPermission(props.slug, scope, manifestPermissions.value)
}

const scopeConsent = useBridgeScopeConsent({
  isPreGranted: (scope) => hasInstalledPermission(
    props.slug,
    scope,
    manifestPermissions.value.length ? manifestPermissions.value : null,
  ),
  onAccepted: onRuntimeScopeGranted,
})

const scopeConsentScopes = computed(() =>
  scopeConsent.dialog.scope ? [scopeConsent.dialog.scope] : [],
)

const scopeConsentLabels = computed(() =>
  scopeConsentScopes.value.map((scope) =>
    bridgeScopeLabel(scope, props.slug, translateBridgeKey),
  ),
)

const scopeConsentMessage = computed(() => {
  const template = labels.value.bridge_perm_runtime_message
  return template.replace(/\{app\}/g, props.slug)
})

function hubDisplayUser(moduleStore) {
  const user = moduleStore?.zoneContext?.state?.user
  if (!user || user.id == null) return null
  return { id: user.id, name: user.name ?? String(user.id) }
}

const { mount: mountBridge, sendReady: sendBridgeReady } = useRunnerBridge({
  iframeRef,
  launchContext,
  launchUrl,
  slug: props.slug,
  appName: props.slug,
  isHosted: () => isHosted.value,
  entryUrl: () => props.entryUrl,
  getDisplayUser: () => hubDisplayUser(moduleStore),
  getBridgeApiBase: () => backendUrl.value || null,
  getPublisherApiBase: () => resolveAppApiUrls({ api_urls: props.apiUrls })[0] ?? null,
  getManifestPermissions: () => manifestPermissions.value,
  bridgeDesktopMessage: (token, slug, payload) => api?.bridgeDesktopMessage?.(token, slug, payload),
  requestScopeConsent: scopeConsent.requestScopeConsent,
  onSessionScopeGranted: onRuntimeScopeGranted,
  onDesktopMessage(payload) {
    const title = String(payload?.title ?? '').trim()
    const body = String(payload?.body ?? '').trim()
    if (!title && !body) return
    notifications?.info(body || title, body ? title : '')
  },
  onNotify(payload) {
    const title = String(payload?.title ?? '').trim()
    const body = String(payload?.body ?? '').trim()
    if (!title && !body) return
    notifications?.info(body || title, body ? title : '')
  },
  onTaskbarBadge() {
    /* badge UI deferred — scope recorded server-side */
  },
})

const preflightTargetLabel = computed(() => {
  if (isHosted.value) return props.slug
  return props.entryUrl || labels.value.not_configured
})

const isDraft = computed(() => props.status === 'draft')
const showPreflight = computed(() => isDraft.value && !launched.value)

const iframeSandbox = computed(() =>
  iframeSandboxAttrs(isHosted.value ? RUNTIME_HOSTED : props.runtimeType),
)

const loading = ref(false)
const launching = ref(false)
const launched = ref(false)
const error = ref('')
const preflightError = ref('')
const launchRuntimeType = ref(props.runtimeType)
const pinging = ref(false)
const pingResult = ref(null)
const safeResult = ref(null)

const pingLabel = computed(() => {
  if (!pingResult.value) return ''
  if (pingResult.value.ok) {
    const ms = pingResult.value.latency_ms != null ? ` · ${pingResult.value.latency_ms} ms` : ''
    return `${labels.value.ping_ok}${ms}`
  }
  return labels.value.ping_fail
})

function launchOptions() {
  return {
    backendUrl: backendUrl.value,
    runtimePublicUrl: moduleOptions?.runtimePublicUrl ?? '',
    runtimeType: launchRuntimeType.value,
  }
}

async function onPing() {
  if (!api?.ping || !props.slug) return
  pinging.value = true
  pingResult.value = null
  preflightError.value = ''
  try {
    const res = await api.ping(props.slug)
    pingResult.value = res?.data?.data ?? { ok: false }
  } catch {
    pingResult.value = { ok: false }
  } finally {
    pinging.value = false
  }
}

function onCheckSafe() {
  safeResult.value = null
  preflightError.value = ''
  if (!props.entryUrl) {
    safeResult.value = false
    return
  }
  safeResult.value = isEntryUrlAllowed(props.entryUrl, allowedOrigins.value)
}

function isBackendReady() {
  return Boolean(moduleOptions?.hasToken && moduleOptions?.backendUrl)
}

async function doLaunch() {
  if (!props.slug) {
    error.value = labels.value.err_generic
    return
  }

  if (!isBackendReady()) {
    loading.value = true
    return
  }

  if (!api?.launch) {
    error.value = labels.value.err_generic
    return
  }

  loading.value = true
  launching.value = true
  error.value = ''
  preflightError.value = ''

  try {
    const launchBody = props.installedVersion ? { version: props.installedVersion } : {}
    const res = await api.launch(props.slug, launchBody)
    if (!res?.data) {
      error.value = labels.value.err_generic
      return
    }

    const data = res?.data?.data ?? res?.data ?? {}
    launchRuntimeType.value = data.runtime_type ?? props.runtimeType
    const candidate = resolveLaunchUrl(res?.data)
    const hostedRuntime = launchRuntimeType.value === RUNTIME_HOSTED || launchRuntimeType.value === 'hosted'
    if (!candidate) {
      error.value = hostedRuntime ? labels.value.err_no_bundle : labels.value.err_no_entry
      return
    }
    if (!isAllowedLaunchUrl(candidate, allowedOrigins.value, launchOptions())) {
      error.value = labels.value.safe_fail
      return
    }
    launchUrl.value = hostedRuntime
      ? appendHostedFrameAncestorParams(candidate, {
        hubOrigin: typeof window !== 'undefined' ? window.location.origin : '',
        productOrigin: moduleOptions?.productOrigin ?? '',
      })
      : candidate
    const launchToken = data.launch_token ?? null
    const scopesGranted = Array.isArray(data.scopes_granted) ? [...data.scopes_granted] : []
    launchContext.value = {
      launch_token: launchToken,
      session_id: data.session_id ?? null,
      scopes_granted: scopesGranted,
      slug: data.slug ?? props.slug,
    }
    launched.value = true
    mountBridge()
  } catch {
    error.value = labels.value.err_generic
  } finally {
    loading.value = false
    launching.value = false
  }
}

function onIframeLoad() {
  injectRuntimeDocumentScrollbarsIntoIframe(iframeRef.value)
  if (launchContext.value?.launch_token) {
    sendBridgeReady()
  }
}

function onLaunchClick() {
  if (!isHosted.value && props.entryUrl && !isEntryUrlAllowed(props.entryUrl, allowedOrigins.value)) {
    safeResult.value = false
    preflightError.value = labels.value.safe_fail
    return
  }
  doLaunch()
}

watch(
  () => isBackendReady(),
  (ready) => {
    if (isDraft.value || launchUrl.value || error.value) return
    if (!ready) {
      if (!isDraft.value) loading.value = true
      return
    }
    launched.value = true
    doLaunch()
  },
  { immediate: true },
)
</script>
