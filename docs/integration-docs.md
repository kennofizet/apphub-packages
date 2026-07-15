# App Hub integration docs (JSON contract)

## Purpose

`packages/backend/src/Modules/Bridge/Resources/integration-docs.json` — single source of truth for humans and AI tools.

| Audience | Who | What they read |
|----------|-----|----------------|
| `end_user` | Uses Hub desktop | Guide → **For users** |
| **`publisher`** | App author | **`audiences.publisher.bridge`** — **usage only** |
| `host_dev` | Host platform team | Package install + **`backend_security`** |
| `agent` | AI helper | Publisher text = bridge usage; security stays internal |

---

## Publisher docs = usage only

Publisher documentation explains **how to use** App Hub from an app:

- **Runtime types** — `hosted` (zip on Hub) vs `iframe` (your `entry_url` SPA); see `audiences.publisher.runtime_types`
- Launch flow (`POST /apps/{slug}/launch` — server mints `scopes_granted` + short-lived `launch_token` + `expires_in`; Hub auto-refreshes while the window is open)
- **Parent bridge demo mode** — before DEV approve (draft / pending), `callParent` may return host demo fixtures; runner shows a Demo badge; after approve + relaunch, real `parent.*` scopes apply
- **Hosted storage** — automatic `localStorage` shim; `window.__APPHUB_STORAGE__.ready` (hosted only)
- **Hosted troubleshooting** — zip contract, ES modules (`type="module"`), CSP `frame-ancestors`, runtime serve; see `audiences.publisher.hosted_runtime_troubleshooting` (schema ≥ 1.11.0)
- `POST /apps/{slug}/install-intent` when permission dialog opens
- `POST /apps/{slug}/bridge-consents` with `intent_token` after user Accept
- `AppHubBridge` handshake — full `bridge:ready` context (`bridge_api_base`, `publisher_api_base`, `app_version`, …); listen again after Hub refreshes `launch_token`
- `getDisplayUser()` for UI; publisher backend `GET bridge/user` for verified identity
- `requestPermission`, `sendDesktopMessage`, `setTaskbarBadge` for Hub desktop UI (postMessage)
- `POST bridge/notify` from publisher backend (`api_urls`) for inbox notifications — same pattern as `GET bridge/user`
- `reportError(error)` — logs runtime errors to usage (`action: error`); no extra permission

Publisher docs **do not** mention:

- Production, host package, `host_dev`, `installAppHubModule`
- Security architecture or isolation (that is backend / host_dev)

Human copy: **Guide → App bridge** tab. Field reference: [manifest-schema.md](./manifest-schema.md).

---

## Backend security (host_dev only)

Endpoint security lives in **`audiences.host_dev.backend_security`** in JSON. Publishers do not receive this section in human-facing docs.

Human checklist: [host-security.md](./host-security.md) (trusted proxies, rate limits, notify fan-out, embed flags).

Host backend must:

- Enforce scopes on every `bridge/*` call
- Validate `launch_token` per app slug + session; `user_id` from `knf_core_user_id` (not client headers)
- Keep `launch_token` short (`APPHUB_LAUNCH_TOKEN_TTL`); allow Hub `POST …/launch/refresh` within `APPHUB_LAUNCH_SESSION_MAX_TTL`
- Refresh recomputes `scopes_granted` from live consent; uninstall/revoke deletes launch sessions
- Configure `defaults.demo_data` in `apphub-parent-bridge.php` for publisher draft/pending testing
- On DEV approve: sync owner parent consents for the approved version (no reinstall required)
- `POST /apps/{slug}/bridge-consents` records manifest permissions server-side after install accept
- `POST /apps/{slug}/launch` mints token scopes from server consent DB only
- `GET integration-docs/internal` only with `X-AppHub-Host-Access` matching `APPHUB_HOST_ACCESS_SECRET` (host integrator — **not** packages-core zone/server manager users)
- Return only zone-safe user fields packages-core allows

Publishers only see granted bridge responses — not how enforcement works.

### Hosted runtime framing (host_dev)

When the product embeds Hub in an iframe and users open **hosted** apps, the browser enforces CSP **`frame-ancestors`** on `GET …/apps/{slug}/runtime/{path}`. **Both** origins must be allowed:

| Laravel env | Role |
|-------------|------|
| `APPHUB_ALLOWED_HUB_ORIGINS` | Hub SPA (e.g. `https://apphub.yourcompany.com`) |
| `APPHUB_ALLOWED_PRODUCT_ORIGINS` | Product shell that embeds Hub (e.g. `https://app.yourcompany.com`) |

Parent `postMessage` must include `productOrigin: window.location.origin`. Hub forwards `hub_origin` and `product_origin` on the hosted launch URL. Details: [hub-host-starter/README.md](../hub-host-starter/README.md).

### Iframe entry_url policy (host_dev)

| Laravel env | Role |
|-------------|------|
| *(catalog)* | Per-app `apps.entry_url` + DEV approval — primary allowlist |
| `APPHUB_ALLOWED_RUNTIME_ORIGINS` | Optional enterprise cap on publisher origins. Exact `http://host:port` entries allow intranet HTTP for those origins only (`ALLOW_ANY` never implies HTTP) |
| `APPHUB_ALLOW_ANY_PUBLISHER_RUNTIME_ORIGIN` | Production opt-in when enterprise list is empty (HTTPS only; catalog+DEV model) |

Iframe runtime uses `allow-same-origin` on the **publisher** origin only — Hub `localStorage` stays isolated.

### Hosted storage shim (host_dev)

Hosted zips receive an injected `localStorage` proxy (`apphub:storage` postMessage). Hub persists per user + slug. See `audiences.host_dev.hosted_storage` and `audiences.publisher.hosted_storage` in JSON.

---

## Delivery

| Channel | Location |
|---------|----------|
| HTTP (publisher) | `GET {api_prefix}/apphub/integration-docs` — public filtered subset (no token) |
| HTTP (host dev) | `GET {api_prefix}/apphub/integration-docs/internal` — full JSON |
| JSON source | `packages/backend/src/Modules/Bridge/Resources/integration-docs.json` |
| Publisher (human) | Guide → **App bridge** |
| Host security | `audiences.host_dev.backend_security` (internal endpoint only) |

## Versioning

- **Patch**: doc clarifications
- **Minor**: new bridge methods, scopes, or publisher contract fields (e.g. `1.9.0` → `1.10.0` runtime_types + hosted_storage; `1.11.0` hosted_runtime_troubleshooting; `1.12.0` app icons, desktop.download/saveFile, hub_locale/color_scheme; `1.15.0` parent demo mode, launch refresh / `expires_in`)
- **Major**: breaking bridge or launch contract
