import axios from 'axios'

const REQUEST_TIMEOUT_MS = 30_000

/**
 * HTTP client for apphub-backend.
 */
export function createAppHubApi(backendUrl, token, options = {}) {
  const baseURL = (backendUrl || '').replace(/\/$/, '')
  const hostAccessSecret = options.hostAccessSecret || ''
  const getZoneHeaderId = options.getZoneHeaderId

  const client = axios.create({
    baseURL,
    timeout: REQUEST_TIMEOUT_MS,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { 'X-Knf-Token': token } : {}),
    },
  })

  client.interceptors.request.use((config) => {
    const zoneId = typeof getZoneHeaderId === 'function' ? getZoneHeaderId() : null
    if (zoneId) {
      config.headers = config.headers || {}
      config.headers['X-Knf-Zone-Id'] = zoneId
    }
    return config
  })

  function hostHeaders() {
    return hostAccessSecret ? { 'X-AppHub-Host-Access': hostAccessSecret } : {}
  }

  return {
    post: (path, data, config) => client.post(path, data, config),
    bootstrap: () => client.get('/bootstrap'),
    integrationDocs: () => client.get('/integration-docs'),
    integrationDocsInternal: () =>
      client.get('/integration-docs/internal', { headers: hostHeaders() }),
    apps: (params) => client.get('/apps', { params }),
    launch: (slug, payload) => client.post(`/apps/${encodeURIComponent(slug)}/launch`, payload ?? {}),
    recordBridgeConsent: (slug, payload) =>
      client.post(`/apps/${encodeURIComponent(slug)}/bridge-consents`, payload ?? {}),
    revokeBridgeConsents: (slug) =>
      client.delete(`/apps/${encodeURIComponent(slug)}/bridge-consents`),
    createInstallIntent: (slug, payload) =>
      client.post(`/apps/${encodeURIComponent(slug)}/install-intent`, payload ?? {}),
    ping: (slug) => client.post(`/apps/${encodeURIComponent(slug)}/ping`),
    verifyLaunchToken: (launchToken, appSlug) =>
      client.post('/verify-launch-token', {
        launch_token: launchToken,
        ...(appSlug ? { app_slug: appSlug } : {}),
      }),
    usage: (slug, payload) =>
      client.post(`/apps/${encodeURIComponent(slug)}/usage`, payload),
    parentBridgeCall: (action, args) =>
      client.post('/parent-bridge/call', { action, args: args ?? {} }),
    parentBridgeEvent: (name, payload) =>
      client.post('/parent-bridge/event', { name, payload: payload ?? {} }),
    parentBridgeScopePrompts: () => client.get('/parent-bridge/scope-prompts'),
    devApps: (params) => client.get('/dev/apps', { params }),
    devInspectBundle: (slug) =>
      client.get(`/dev/apps/${encodeURIComponent(slug)}/bundle-inspect`),
    devReadBundleFile: (slug, path, options = {}) =>
      client.get(`/dev/apps/${encodeURIComponent(slug)}/bundle-file`, {
        params: {
          path,
          ...(options.compare ? { compare: 1 } : {}),
        },
      }),
    devDisableApp: (slug) =>
      client.post(`/dev/apps/${encodeURIComponent(slug)}/disable`),
    devSetAppStatus: (slug, status) =>
      client.post(`/dev/apps/${encodeURIComponent(slug)}/status`, { status }),
    devRejectPendingVersion: (slug) =>
      client.post(`/dev/apps/${encodeURIComponent(slug)}/reject-pending`),
    registerApp: (payload) => {
      if (typeof FormData !== 'undefined' && payload instanceof FormData) {
        return client.post('/apps/register', payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
          timeout: 120_000,
        })
      }

      return client.post('/apps/register', payload, { timeout: 120_000 })
    },
    appVersions: (slug) => client.get(`/apps/${encodeURIComponent(slug)}/versions`),
    notifications: (params) => client.get('/notifications', { params }),
    notificationsSummary: () => client.get('/notifications/summary'),
    notificationsDismiss: (ids) => client.post('/notifications/dismiss', { ids }),
    notificationsReadAll: () => client.post('/notifications/read-all'),
  }
}
