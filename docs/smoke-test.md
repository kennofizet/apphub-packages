# App Hub smoke test checklist

Manual pass on `____TEST/test` (or production host). Run after package upgrades or before release.

## Prerequisites

- Backend: `composer require kennofizet/apphub-backend`, migrate, `.env` with `APPHUB_*`
- Frontend: `@kennofizet/apphub-frontend` linked or installed
- Logged-in user with zone access; DEV user in `APPHUB_DEV_USER_IDS` for approve flow
- Hub: `/apphub` or iframe via `hub-host-starter`

## Checklist

| # | Step | Expected |
|---|------|----------|
| 1 | Open App Hub desktop | Taskbar, icons, Guide / App Store builtins |
| 2 | App Store lists active apps | `GET /apps?mode=store` — zone RBAC |
| 3 | Install app from store | Desktop icon appears |
| 4 | Open hosted app (`demo-simple`) | Runner loads; bridge Hello (`display_user`) |
| 5 | Verify user (with `local-bridge-proxy`) | `GET …/bridge/user` via proxy succeeds |
| 6 | Open iframe app (`demo-iframe`) | `entry_url` loads; bridge works |
| 7 | Storage POC (optional) | Writer + readers isolated per slug |
| 8 | Drop new zip (windows closed) | Draft registered; owner auto-install |
| 9 | Dev Tools approve draft | App `active` in store for zone |
| 10 | Toggle language (parent iframe) | UI updates; no console `inject` / `provide` warnings |
| 11 | Guide → full publisher reference | Human doc loads; public JSON link works |
| 12 | Open **App Store** (or launch app) | Stale `healthcheck_url` pings automatically; **Unhealthy** badge updates |
| 13 | App Store unhealthy badge | Failed healthcheck shows **Unhealthy** (card dimmed) |
| 14 | Disable app (DEV) | Store shows **Offline**; launch blocked |
| 15 | **Report test error** in `demo-simple` or `demo-iframe` | Message confirms send; `apphub_app_usage_logs` row with `action=error` |
| 16 | **Send desktop notify** in demo (`desktop.notify` + `api_urls` + proxy) | `POST …/bridge/notify` via proxy; bell + toast |
| 17 | **Parent bridge** (`callParent`) with product iframe + listener | See below — `null` or data from host config handler |

### Smoke #17 — parent bridge (config-driven)

1. Publish `config/apphub-parent-bridge.php` on host Laravel (`vendor:publish --tag=apphub-config`).
2. Child app `manifest.json` includes `parent_bridge` + `parent.*` permissions, e.g.:

```json
{
  "permissions": ["user.read", "parent.project.list", "parent.events"],
  "parent_bridge": {
    "actions": [{ "name": "project.list", "scope": "parent.project.list" }],
    "events": [{ "name": "bonus.assign", "scope": "parent.events" }]
  }
}
```
3. Product embeds Hub iframe; parent installs [example/product-shell/product-bridge-listener.js](../example/product-shell/product-bridge-listener.js).
4. Child calls `AppHubBridge.callParent('project.list', { query: { page: 1 } })`.
5. Default stub handler returns `{ ok: true, result: null }` — replace handler class in config for real data.

**Security checks (all must pass in production)**

- Child manifest declares action + scope; user granted install consent for that scope
- API body includes `app_slug`, `bridge_scope`, and `session_id` (from launch context)
- `productOrigin` in Hub matches `APPHUB_ALLOWED_PRODUCT_ORIGINS`
- Action has non-null `permission` and passes `hubBridgeCan()` via host permission checker
- Payload under `max_args_bytes`; per-action rate limit enforced

### Smoke #16 — desktop.notify

1. Manifest includes `"desktop.notify"` in `permissions` and `api_urls` (e.g. `http://localhost:51732`).
2. Run `example/local-bridge-proxy` (same as smoke #5 verify user).
3. Open app → **Send desktop notify** (install must include `desktop.notify` on launch token).
4. App calls `POST {api_urls}/bridge/notify` from demo (not Hub postMessage).
5. Click **🔔** → drawer; dismiss with **×** or **Mark all read**.

### Smoke #15 — reportError

1. Open a running app from Hub (**hosted** `demo-simple` or **iframe** `demo-iframe` on `:15180`).
2. Click **Report test error** (repack/re-drop hosted zip if you use an old bundle).
3. App shows: `reportError sent — check apphub_app_usage_logs…`
4. Confirm in DB (test backend):

```bash
php artisan tinker --execute="echo json_encode(\Kennofizet\AppHub\Modules\Launch\Models\AppUsageLog::query()->where('action','error')->orderByDesc('id')->first()?->metadata);"
```

### Smoke #12–13 — unhealthy badge

1. Ensure iframe demo has `healthcheck_url` (e.g. `http://localhost:15180/health`).
2. **Stop** `demo-iframe` server → open **App Store** → card shows **Unhealthy** (red badge, dimmed).
3. **Start** server → reopen App Store (or wait TTL ~5 min) → badge clears.

### Smoke #14 — disable

1. **Dev Tools** → disable an active app.
2. App Store shows **Offline**; launch blocked.

## Healthcheck (automatic)

Health status updates **without** running artisan manually:

| Trigger | When |
|---------|------|
| **App Store load** | `GET /apps?mode=store` re-pings apps whose last check is older than `APPHUB_HEALTHCHECK_TTL_SECONDS` (default 5 min) |
| **Launch** | Stale check refreshed when user opens an app |
| **DEV approve** | First ping when app goes `active` (if `healthcheck_url` set) |
| **Scheduler** | `apphub:healthcheck` every `APPHUB_HEALTHCHECK_SCHEDULE_MINUTES` (default 5) — requires host cron: `* * * * * php artisan schedule:run` |

Manual ops (optional):

```bash
php artisan apphub:healthcheck
```

## Verify token (publisher backend)

See [example/verify-launch-token/README.md](../example/verify-launch-token/README.md).
