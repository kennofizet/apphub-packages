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

4. Open from desktop → DEV approves draft → test bridge buttons (same pattern as `demo-simple/html`).

5. Run `example/local-bridge-proxy` on `:51732` for **Verify user (API)** and **Send desktop notify** (`shared/publisher-bridge.js`).

## Files

- `html/manifest.json` — Hub catalog manifest + served at `entry_url` for version badge
- `html/publisher-bridge.js` — synced from `example/shared/` (`npm run sync:shared`)
- `html/index.html`, `styles.css`, `app.js` — app UI
- `serve.mjs` — local static server

## Security (iframe sandbox)

Hub loads **approved** iframe apps (`apps.entry_url` + DEV review) with `allow-same-origin` so publisher SPAs (Vue/React on **your** HTTPS origin) can load JS/CSS and use storage **on their own origin**.

That does **not** allow reading App Hub `localStorage` — Hub runs on a different origin (`:5173` vs publisher host). Trust gate = DEV approves each `entry_url`.

Ensure `APPHUB_ALLOW_LOCALHOST_API_URLS=true` (or `APP_ENV=local`) when using `http://localhost:15180`. Catalog `entry_url` + DEV approval is the per-app allowlist. In production with no enterprise list, host sets `APPHUB_ALLOW_ANY_PUBLISHER_RUNTIME_ORIGIN=true`.
