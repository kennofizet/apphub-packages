# App Hub Backend

Laravel package **`kennofizet/apphub-backend`** — catalog, launch tokens, publisher bridge HTTP, hosted runtime serve, and user notification inbox.

Requires **PHP 8.2+**, **Laravel 12**, and **`kennofizet/packages-core-backend`** (session token + zone RBAC).

## Install (host Laravel app)

```bash
composer require kennofizet/apphub-backend
php artisan vendor:publish --tag=apphub-config
php artisan vendor:publish --tag=apphub-migrations
php artisan migrate
```

Routes mount under:

```text
{packages-core.api_prefix}/{apphub.api_prefix}
```

Default: `/api/knf/apphub` (see `config/packages-core.php` and published `config/apphub.php`).

## Modules

| Module | Path | Role |
|--------|------|------|
| **Catalog** | `src/Modules/Catalog` | App store, register/publish, zip bundles, hosted runtime serve, DEV review |
| **Launch** | `src/Modules/Launch` | Launch tokens, healthcheck, usage logs, notification inbox |
| **Bridge** | `src/Modules/Bridge` | Install consent, `bridge/user`, `bridge/notify`, integration docs |

## API groups (middleware)

| Group | Auth | Examples |
|-------|------|----------|
| **User session** | `knf.core.token` | `GET bootstrap`, `GET apps`, `POST apps/{slug}/launch`, notifications |
| **Bridge HTTP** | `X-AppHub-Launch-Token` + caller IP vs manifest `api_urls` | `GET bridge/user`, `POST bridge/notify` |
| **Public** | None (validator only) | `GET integration-docs`, hosted `…/runtime/{path}` |
| **Host integrator** | `X-AppHub-Host-Access` | `GET integration-docs/internal` |

Publisher-facing contract: `GET …/integration-docs` → JSON `audiences.publisher.bridge` (no login).

## Frontend pairing

Use with [@kennofizet/apphub-frontend](../frontend/README.md):

```js
installAppHubModule(app, {
  coreUrl: 'https://your-api/api/knf',
  backendUrl: 'https://your-api/api/knf/apphub',
  token: sessionTokenFromHost,
})
```

`GET bootstrap` returns Hub/runtime origins and `is_dev_user` for the signed-in user.

## Configuration

Published file: `config/apphub.php`. Important env vars:

| Variable | Purpose |
|----------|---------|
| `APPHUB_API_PREFIX` | URL segment (default `apphub`) |
| `APPHUB_HOST_ACCESS_SECRET` | Internal docs + host-only endpoints |
| `APPHUB_ALLOWED_HUB_ORIGINS` | CSP `frame-ancestors` for hosted apps |
| `APPHUB_ALLOWED_PRODUCT_ORIGINS` | Product shell when Hub is iframe-embedded |
| `APPHUB_USE_API_URL_IP_PINS` | DNS IP pins for bridge callers (on in prod by default) |
| `APPHUB_LAUNCH_TOKEN_TTL` | Launch session TTL (seconds, max 180) |

Full production checklist: [docs/host-security.md](../../docs/host-security.md).

## Security notes (host responsibility)

- Configure Laravel **TrustProxies** so bridge IP checks see the real client IP behind nginx/CDN.
- Bridge routes rate-limit per launch token; `verify-launch-token` is one-shot.
- `healthcheck_url` is validated at publish (no private IPs / SSRF).
- `POST bridge/notify` defaults to launching user; `broadcast: true` for org fan-out.

Internal endpoint matrix: `src/Modules/Bridge/Resources/integration-docs.json` → `audiences.host_dev.backend_security`.

## Scheduled tasks

Background healthcheck (optional):

```bash
# Host cron — every minute
php artisan schedule:run
```

Package registers `apphub:healthcheck-apps` when the host runs the scheduler (`APPHUB_HEALTHCHECK_SCHEDULE_MINUTES`, default 5).

## Tests

```bash
cd packages/backend
composer install
vendor/bin/phpunit
```

## Docs in this monorepo

| File | Description |
|------|-------------|
| [docs/manifest-schema.md](../../docs/manifest-schema.md) | `manifest.json` fields |
| [docs/integration-docs.md](../../docs/integration-docs.md) | JSON contract overview |
| [integration-docs.json](./src/Modules/Bridge/Resources/integration-docs.json) | Machine-readable source of truth |
