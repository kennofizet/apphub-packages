# Verify launch token (publisher tool backend)

When a user opens your app, App Hub mints a short-lived **`launch_token`** (60–180s). Your **publisher server** must verify it before trusting identity or acting on behalf of that user.

**Do not** verify from the browser iframe — call App Hub from your backend (or use the local proxy below for dev).

---

## Flow

```text
1. User opens app → POST /apps/{slug}/launch (Hub, user session)
2. App receives launch_token (query param or apphub:bridge:ready)
3. App sends launch_token to YOUR backend (never log it in client UI)
4. Your backend → POST {apphub}/verify-launch-token
5. Hub returns user_id + scopes_granted (token marked used — one-shot)
6. Your backend issues session / authorizes the request
```

Manifest must list your backend in **`api_urls`** so App Hub accepts the caller IP.

---

## curl (from your server)

Replace `APPHUB_BASE` and use a real token from a test launch (`?launch_token=` in runner URL or bridge context).

```bash
APPHUB_BASE="http://localhost:8000/api/jmm/zz/apphub"
LAUNCH_TOKEN="paste-from-launch-url-or-bridge-ready"
APP_SLUG="demo-simple-html"

curl -sS -X POST "${APPHUB_BASE}/verify-launch-token" \
  -H "Content-Type: application/json" \
  -d "{\"launch_token\":\"${LAUNCH_TOKEN}\",\"app_slug\":\"${APP_SLUG}\"}"
```

**Success (200)** — shape from Hub API:

```json
{
  "success": true,
  "data": {
    "user_id": 1,
    "app_slug": "demo-simple-html",
    "session_id": "…",
    "scopes_granted": ["user.read"],
    "bundle_version": "1.1.75"
  }
}
```

**Errors:** `401` invalid/expired/used token; `403` caller IP not in manifest `api_urls`.

---

## Node.js (minimal)

Run on the same host declared in `manifest.api_urls` (e.g. `localhost` in dev).

```javascript
// verify.mjs — Node 18+
const APPHUB_BASE = process.env.APPHUB_BACKEND_URL?.replace(/\/$/, '')
if (!APPHUB_BASE) throw new Error('Set APPHUB_BACKEND_URL')

export async function verifyLaunchToken(launchToken, appSlug) {
  const res = await fetch(`${APPHUB_BASE}/verify-launch-token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      launch_token: launchToken,
      ...(appSlug ? { app_slug: appSlug } : {}),
    }),
  })
  const json = await res.json().catch(() => ({}))
  if (!res.ok) {
    throw new Error(json?.message || json?.error || `verify failed (${res.status})`)
  }
  return json?.data ?? json
}

// CLI: node verify.mjs <launch_token> [app_slug]
if (import.meta.url === `file://${process.argv[1]?.replace(/\\/g, '/')}`) {
  const token = process.argv[2]
  const slug = process.argv[3]
  if (!token) {
    console.error('Usage: APPHUB_BACKEND_URL=... node verify.mjs <launch_token> [app_slug]')
    process.exit(1)
  }
  verifyLaunchToken(token, slug)
    .then((data) => { console.log(JSON.stringify(data, null, 2)) })
    .catch((err) => { console.error(err.message); process.exit(1) })
}
```

This repo includes a runnable copy: [`verify.mjs`](./verify.mjs).

---

## Local dev without a real backend

Use [`../local-bridge-proxy`](../local-bridge-proxy) — forwards `/verify-launch-token` and `/bridge/user` to App Hub so the demo app can `fetch('http://localhost:51732/bridge/user')` with headers:

- `X-AppHub-Launch-Token`
- `X-AppHub-App-Slug`

Match `api_urls` in `demo-simple/html/manifest.json` to the proxy port.

---

## After verify

- Use `user_id` + `scopes_granted` for authorization on your API.
- For display name in UI only, use `display_user` from `apphub:bridge:ready` — not for auth.
- For ongoing user profile, call `GET bridge/user` with the launch token (before it is consumed) or design your session after verify.
