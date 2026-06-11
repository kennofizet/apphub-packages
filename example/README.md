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

Optional: `api_base_url`, `healthcheck_url`.

### Version rules (automatic)

| Case | Result |
|------|--------|
| New `slug` | Creates **draft** `1.0.0` (or your version) |
| Same `slug`, you are **owner**, version **>** current | New draft version uploaded; app returns to **draft** for DEV approval |
| Same `slug`, version **≤** current | Error: `Version must be greater than X` |
| Same `slug`, **not** owner | Error: `App slug already exists` |

No `"action": "replace"` — bump `version` in `manifest.json` and drop again.

**Installed users keep their pinned version** until they open App Store and tap **Update**. Dropping a new version as owner does not change what you (or others) already run.

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

Zip these files together (flat root):

- `manifest.json`
- `index.html`
- `styles.css`
- `app.js`

Bump `version` in `manifest.json` before re-uploading the same slug.

---

## Vue demo

```bash
cd example/demo-simple/vue
npm install
npm run pack
```

Drop **`release/demo-simple-vue.zip`** on the Hub desktop.

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

- `node_modules/`, `dist/`, and `release/` are gitignored.
- Version history is stored per app; DEV / owner can view it in App Store → Settings.
