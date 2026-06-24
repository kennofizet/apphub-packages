# Local publisher bridge proxy

Simulates a **publisher tool backend** for local testing when you do not have a real server yet.

## Why

App Hub enforces `manifest.api_urls` by checking the **TCP client IP** of the caller. Browsers cannot call App Hub bridge APIs directly.

Flow:

```text
Demo app  →  fetch http://localhost:51732/bridge/user  →  this proxy
  →  App Hub API (caller IP 127.0.0.1 = localhost in api_urls)  →  OK
```

## Run

**PowerShell** (Windows) — recommended:

```powershell
cd example/local-bridge-proxy
.\start.ps1
```

`start.ps1` reads `VITE_APPHUB_BACKEND_URL` from `____TEST/test/apphub-host-starter/.env` when present.

Manual:

```powershell
cd example/local-bridge-proxy

$env:APPHUB_BACKEND_URL = "http://localhost:8000/api/jmm/zz/apphub"
$env:PORT = "51732"

node server.mjs
```

Use the same base URL as `VITE_APPHUB_BACKEND_URL` in your hub-host-starter `.env` (no trailing slash).

Match `PORT` with `api_urls` in your app `manifest.json`:

```json
"api_urls": ["http://localhost:51732"]
```

Requires **Node.js 18+** (uses global `fetch`).

## Proxied routes

- `GET /bridge/user`
- `POST /bridge/notify`
- `POST /verify-launch-token`

Demos use [`shared/publisher-bridge.js`](../shared/publisher-bridge.js).

Forwards `X-AppHub-Launch-Token` and `X-AppHub-App-Slug` unchanged.

## Optional strict mode (advanced)

To also enforce manifest **port** on localhost (not enabled by default), set the same secret on Hub and proxy:

```env
APPHUB_BRIDGE_PROXY_SECRET=your-local-secret
```

Hub then requires `X-AppHub-Bridge-Proxy-Secret` + `X-AppHub-Publisher-Origin` from the proxy. Leave unset for normal local dev.

See also [`../verify-launch-token/README.md`](../verify-launch-token/README.md).
