# App Hub `manifest.json` schema

Publisher contract for registering apps on App Hub. Parsed by `AppManifestParser` in `apphub-backend`.

Machine-readable bridge fields: `integration-docs.json` → `audiences.publisher.bridge.manifest`.

---

## Runtime types

| `runtime_type` | Publish method | Required extra fields |
|----------------|----------------|------------------------|
| `hosted` | Drop `.zip` with `manifest.json` inside (or `POST /apps/register` multipart) | `main` optional (default `index.html`) |
| `iframe` | Drop `manifest.json` on desktop or `POST /apps/register` JSON | `entry_url` (HTTPS in production) |

Local-only install (no server listing): drop a manifest with `"type": "apphub-local"` — not covered here.

---

## Common fields (hosted + iframe)

| Field | Type | Required | Rules |
|-------|------|----------|--------|
| `slug` | string | yes | `^[a-z0-9][a-z0-9_-]{0,63}$` — catalog id |
| `name` | string | yes | Display name, max 255 |
| `version` | string | yes | Semver e.g. `1.0.0`; must increase on re-publish by same owner |
| `runtime_type` | string | yes | `hosted` or `iframe` |
| `description` | string | no | Alias: `short_description`, max 500 |
| `icon` | string | no | Emoji or short label, max 32 (default 📦) |
| `permissions` | string[] | recommended | Bridge scopes — see below |
| `api_urls` | string[] | no | Publisher tool backend URLs; required for server-side `bridge/user` or `verify-launch-token` |
| `api_base_url` | string | no | Legacy single URL; merged into `api_urls` |
| `healthcheck_url` | string | no | Optional ping target for `POST /apps/{slug}/ping` |

### `permissions` (bridge scopes)

| Scope | Purpose |
|-------|---------|
| `user.read` | User id + display name (authoritative via `GET bridge/user`) |
| `user.profile` | Extended profile when user agrees |
| `desktop.notify` | Toast / notification on desktop |
| `desktop.message` | Banner on desktop work area |
| `desktop.badge` | Taskbar badge on app button |

Granted at **install** (server records consent). Desktop scopes can also be requested at runtime via `AppHubBridge.requestPermission`.

### `api_urls`

- Only needed when your **publisher backend** calls App Hub HTTP (`GET bridge/user`, `POST verify-launch-token`).
- App Hub matches the caller **TCP IP** to DNS of hosts in `api_urls` (pinned at publish in production).
- `localhost` / `127.0.0.1` allowed in local dev when `APPHUB_ALLOW_LOCALHOST_API_URLS` or `APP_ENV=local`.
- Browser apps must call **your** backend, not App Hub directly.

---

## Hosted-only fields (`runtime_type: "hosted"`)

| Field | Type | Required | Notes |
|-------|------|----------|--------|
| `main` | string | no | Entry file (default `index.html`); aliases: `bundle_entry`, `entry` |
| `type` | string | no | Default `module` |
| `keywords` | string[] | no | Max 20 items |
| `author` | string or `{ name, email }` | no | |
| `license` | string | no | e.g. `MIT` |

Zip layout: `manifest.json` at zip root or one subfolder. Blocked: `.php`, `.exe`, `node_modules`, dangerous paths.

**Storage:** Hub injects a `localStorage` shim — use normal `localStorage` in code; `await window.__APPHUB_STORAGE__?.ready` before first read on load.

### Hosted example

```json
{
  "slug": "my-hosted-app",
  "name": "My Hosted App",
  "version": "1.0.0",
  "description": "Runs from a zip on App Hub",
  "icon": "📦",
  "runtime_type": "hosted",
  "main": "index.html",
  "permissions": ["user.read", "desktop.message"],
  "api_urls": ["http://localhost:51732"]
}
```

---

## Iframe-only fields (`runtime_type: "iframe"`)

| Field | Type | Required | Notes |
|-------|------|----------|--------|
| `entry_url` | string | yes | Publisher SPA URL; alias: `runtime_url` |
| `healthcheck_url` | string | no | Server-side preflight via Hub `POST /apps/{slug}/ping` |

`entry_url` must pass `AppEntryUrlGuard` (HTTPS in production; catalog + DEV approval is the per-app allowlist).

### Iframe example

```json
{
  "slug": "my-iframe-app",
  "name": "My Iframe App",
  "version": "1.0.0",
  "description": "Self-hosted SPA",
  "icon": "🌐",
  "runtime_type": "iframe",
  "entry_url": "https://tools.example.com/apps/my-app/",
  "healthcheck_url": "https://tools.example.com/apps/my-app/health",
  "permissions": ["user.read"]
}
```

---

## Version rules

| Case | Result |
|------|--------|
| New `slug` | Creates **draft** at given version |
| Same `slug`, owner, version **>** current | New version queued for DEV approval |
| Same `slug`, version **≤** current | Rejected |
| Same `slug`, not owner | `App slug already exists` |

Installed users keep **pinned** `installedVersion` until App Store **Update**.

---

## See also

- [example/README.md](../example/README.md) — pack, drop, demos
- [example/verify-launch-token/README.md](../example/verify-launch-token/README.md) — tool backend verify flow
- [integration-docs.md](./integration-docs.md) — contract overview
