import axios from 'axios'

const REQUEST_TIMEOUT_MS = 30_000

/**
 * HTTP client for apphub-backend (optional until backend is wired).
 */
export function createAppHubApi(backendUrl, token, options = {}) {
  const baseURL = (backendUrl || '').replace(/\/$/, '')
  const hostAccessSecret = options.hostAccessSecret || ''

  const client = axios.create({
    baseURL,
    timeout: REQUEST_TIMEOUT_MS,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { 'X-Knf-Token': token } : {}),
    },
  })

  function bridgeHeaders(launchToken, appSlug) {
    return {
      'X-AppHub-Launch-Token': launchToken,
      'X-AppHub-App-Slug': appSlug,
    }
  }

  function hostHeaders() {
    return hostAccessSecret ? { 'X-AppHub-Host-Access': hostAccessSecret } : {}
  }

  return {
    bootstrap: () => client.get('/bootstrap'),
    integrationDocs: () => client.get('/integration-docs'),
    integrationDocsInternal: () =>
      client.get('/integration-docs/internal', { headers: hostHeaders() }),
    apps: (params) => client.get('/apps', { params }),
    launch: (slug) => client.get(`/apps/${encodeURIComponent(slug)}/launch`),
    grantBridgeScope: (launchToken, scope) =>
      client.post('/bridge/scopes', { launch_token: launchToken, scope }),
    bridgeUser: (launchToken, appSlug) =>
      client.get('/bridge/user', { headers: bridgeHeaders(launchToken, appSlug) }),
    bridgeDesktopMessage: (launchToken, appSlug, payload) =>
      client.post('/bridge/desktop/message', payload, {
        headers: bridgeHeaders(launchToken, appSlug),
      }),
  }
}
