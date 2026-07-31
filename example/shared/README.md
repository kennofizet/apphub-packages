# Shared example modules

Publisher-facing helpers copied into demo `html/` folders.

## `publisher-bridge.js`

Use in publisher apps (copy or adapt — not shipped with `@kennofizet/apphub-frontend`).

**Hub postMessage** (`callBridge`):

- `reportError`
- `sendDesktopMessage`
- `applyDesktopTheme`
- `setTaskbarBadge`
- `requestPermission`

**Publisher HTTP** via manifest `api_urls` + [`local-bridge-proxy`](../local-bridge-proxy/):

- `fetchBridgeUser()` → `GET …/bridge/user`
- `fetchBridgeNotify({ title, body })` → `POST …/bridge/notify`

```html
<script src="./publisher-bridge.js"></script>
<script>
  const bridge = AppHubPublisherBridge.createPublisherBridge({
    getUrlLaunchToken: () => new URLSearchParams(location.search).get('launch_token'),
  })
  window.addEventListener('message', (e) => bridge.handleBridgeMessage(e.data))
  // await bridge.fetchBridgeUser()
  // await bridge.fetchBridgeNotify({ title: 'Hi', body: '…' })
</script>
```

Sync into demos:

```bash
npm run sync:shared
```

Runs automatically before `npm run pack` and `npm run serve:iframe`.
