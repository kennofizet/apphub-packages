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
| 15 | `AppHubBridge.reportError(err)` in demo | `app_usage_logs` row with `action=error` |

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
