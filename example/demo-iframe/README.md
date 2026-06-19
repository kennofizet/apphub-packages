# Demo Iframe (HTML)

Minimal **self-hosted** app for testing iframe register (`runtime_type: iframe`, no zip).

| Item | Value |
|------|--------|
| Slug | `demo-iframe-html` |
| Default `entry_url` | `http://localhost:15180/` |
| Hub register | Drop `html/manifest.json` on desktop (or `release/demo-iframe-manifest.json` after `npm run pack`) |

## Quick test

1. **Serve the app** (must match `entry_url` in manifest):

   ```bash
   cd example/demo-iframe
   node serve.mjs
   ```

2. **Bump manifest version** (when re-registering):

   ```bash
   cd example
   npm run pack
   ```

3. **Drop** `example/release/demo-iframe-manifest.json` on App Hub desktop (close windows first).

4. Open from desktop → DEV approves draft → test bridge buttons like `demo-simple/html`.

5. Optional: run `example/local-bridge-proxy` on `:51732` for **Verify user (API)**.

## Files

- `html/manifest.json` — Hub catalog manifest + served at `entry_url` for version badge
- `html/index.html`, `styles.css`, `app.js` — app UI
- `serve.mjs` — local static server

## Security (strict sandbox)

Hub loads iframe apps **without** `allow-same-origin`. That does **not** let the publisher read App Hub `localStorage` — Hub runs on a **different origin** (e.g. `:5173` vs `:15180`). The browser blocks cross-origin storage access either way.

Strict sandbox means the app also **cannot** `fetch('./manifest.json')` on its own host (opaque origin). Use **Hub bridge** (`apphub:bridge:ready` → `publisher_api_base`, `app_version`, `launch_token`) and your **publisher backend** for API calls — see `app.js` in this demo.

Ensure `APPHUB_ALLOW_LOCALHOST_API_URLS=true` and matching `APPHUB_ALLOWED_RUNTIME_ORIGINS` in your test host `.env` when using `http://localhost:15180`.
