# App Hub — examples

Publisher-facing **sample apps** for testing the hosted runtime. These live outside `packages/` and are **not** shipped with the Hub module.

## `demo-simple/`

Two minimal hosted bundles you can zip and drop on the App Hub desktop.

| Folder | Stack | App slug |
|--------|--------|----------|
| `demo-simple/html/` | HTML + CSS + JS | `demo-simple-html` |
| `demo-simple/vue/` | Vue 3 + Vite | `demo-simple-vue` |

Each folder includes a `manifest.json`. Hub reads **only** that file from inside the zip (no manual fields in the UI).

### `manifest.json` (required in zip)

Package-style fields (like `package.json`):

```json
{
  "slug": "my-app",
  "name": "my-app",
  "version": "1.0.0",
  "description": "Short description",
  "main": "index.html",
  "type": "module",
  "keywords": ["demo", "hosted"],
  "author": "Your Name",
  "license": "MIT",
  "icon": "📦",
  "runtime_type": "hosted"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `slug` | yes | Hub catalog id (`a-z0-9`, max 64) |
| `name` | yes | Display / package name |
| `version` | yes | Semver e.g. `1.0.0` |
| `description` | no | Shown in App Store |
| `main` | no | Entry file (default `index.html`) |
| `type` | no | Default `module` |
| `keywords` | no | Array of strings |
| `author` | no | String or `{ "name", "email" }` |
| `license` | no | e.g. `MIT` |
| `runtime_type` | yes | Must be `hosted` for zip publish |

Required for zip publish: `permissions`. Optional: `api_urls` (only if your tool backend calls `bridge/user` or `verify-launch-token`), `healthcheck_url`.

`permissions` — bridge scopes users grant on install (`user.read`, `desktop.notify`, …).

`api_urls` — **optional**. Only needed when a **publisher tool backend** calls App Hub (`verify-launch-token`, `bridge/user`). Apps that only use `display_user` and postMessage bridge can omit it. When set, DEV reviews these in Dev Tools; App Hub checks caller TCP IP against the declared host.

### Version rules (automatic)

| Case | Result |
|------|--------|
| New `slug` | Creates **draft** `1.0.0` (or your version) |
| Same `slug`, you are **owner**, version **>** current | New version queued for DEV approval; if already **active**, the live version stays in the App Store until approved |
| Same `slug`, version **≤** current | Error: `Version must be greater than X` |
| Same `slug`, **not** owner | Error: `App slug already exists` |

No `"action": "replace"` — bump `version` in `manifest.json` and drop again.

**Installed users keep their pinned version** until they open App Store and tap **Update**. Dropping a new version as owner does not change what you (or others) already run.

---

## Quick pack (both demos)

From **`example/`** (one command for re-testing on Hub):

```bash
cd example
npm install
npm run pack
```

This will:

1. **Bump patch version** in `demo-simple/html/manifest.json` and `demo-simple/vue/manifest.json`
2. **Delete old `.zip` files** in `example/release/` and demo `release/` folders
3. **Build** the Vue demo and create fresh zips:

| Output | Slug |
|--------|------|
| `release/demo-simple-html.zip` | `demo-simple-html` |
| `release/demo-simple-vue.zip` | `demo-simple-vue` |

Drop both zips on the App Hub desktop (close windows first). Each run increments version so re-upload succeeds.

---

## Publish on App Hub (test host)

1. Build a zip with `manifest.json` and your app files at the **zip root** (or one subfolder).
2. Log in to App Hub (`/apphub`).
3. **Close all open windows** on the desktop.
4. **Drop the `.zip`** on the desktop.
5. The app is registered as **draft** and **installed on your desktop** automatically (you are the owner).
6. Open it from the desktop icon to test; status stays draft until DEV approves.
7. **DEV** (user in `APPHUB_DEV_USER_IDS`): App Store → Settings → **Approve** the draft; use **Version history** to review uploads.

---

## HTML demo

Packed automatically by `npm run pack` from `example/`. Manual zip root files:

- `manifest.json`
- `index.html`
- `styles.css`
- `app.js`

When the app runs inside App Hub, the runtime API requires `launch_token` on every file request (not only `index.html`). The HTML demo appends the token from the page URL when fetching `manifest.json` for the version badge.

After the runner loads:

- **Say hello (display_user)** — UI greeting from Hub bootstrap on `apphub:bridge:ready` (no API, no `user.read` dialog).
- **Verify user (API)** — requires `user.read` on the launch token (accept at install, reopen app). Then **fetch your publisher backend** at `manifest.api_urls[0]/bridge/user` (not App Hub directly). For local dev, run `example/local-bridge-proxy` on that port; the proxy forwards to App Hub and App Hub checks the proxy IP.

See `docs/sdk-stub.js` for the bridge client stub (`getDisplayUser()` for UI; publisher backend for verified identity).

### Local publisher backend proxy

```bash
cd example/local-bridge-proxy
set APPHUB_BACKEND_URL=http://localhost/your-test-host/api/knf/apphub
set PORT=51732
node server.mjs
```

Keep `PORT` in sync with `api_urls` in `demo-simple/html/manifest.json` (default `http://localhost:51732`).

---

## Vue demo

Also packed by `npm run pack` from `example/`. Vue-only:

```bash
cd example/demo-simple/vue
npm install
npm run pack
```

Drop **`release/demo-simple-vue.zip`** (from `example/release/` when using root pack) on the Hub desktop.

### Do not zip the project folder manually

On Windows, `node_modules/.bin/` contains `.cmd` / `.ps1` shims. If you zip the whole `vue/` folder (or include `node_modules`), upload fails with:

> Blocked file type in bundle: .cmd

`npm run pack` builds `dist/`, copies `manifest.json`, and zips **only**:

- `index.html`
- `assets/*.js`, `assets/*.css`
- `manifest.json`

No `.cmd` files — safe to drop on Hub.

---

## Notes

- `node_modules/`, `dist/`, and `release/` are gitignored (including `example/release/`).
- Version history is stored per app; DEV / owner can view it in App Store → Settings.
