# Local publisher bridge proxy

Simulates a **publisher tool backend** for local testing when you do not have a real server yet.

## Why

App Hub enforces `manifest.api_urls` by checking the **TCP client IP** of the caller (DNS of the declared host). Browsers cannot call App Hub bridge APIs directly — `Origin` headers are spoofable.

Flow:

```text
Demo app (iframe)  →  fetch http://localhost:51732/bridge/user  →  this proxy
  →  App Hub API (sees client IP 127.0.0.1 = localhost in api_urls)  →  OK
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

**CMD**:

```cmd
cd example\local-bridge-proxy
set APPHUB_BACKEND_URL=http://localhost/jmm/zz/api/knf/apphub
set PORT=51732
node server.mjs
```

Use the same base URL as `VITE_APPHUB_BACKEND_URL` in your hub-host-starter `.env` (no trailing slash).

Match `PORT` with the first URL in your app `manifest.json` `api_urls`, e.g.:

```json
"api_urls": ["http://localhost:51732"]
```

Requires **Node.js 18+** (uses global `fetch`).

## Proxied routes

- `GET /bridge/user`
- `POST /bridge/desktop/message`
- `POST /verify-launch-token`

Forwards `X-AppHub-Launch-Token` and `X-AppHub-App-Slug` headers unchanged.

See also [`../verify-launch-token/README.md`](../verify-launch-token/README.md) for curl + standalone `verify.mjs`.
