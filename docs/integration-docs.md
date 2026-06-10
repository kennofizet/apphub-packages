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

- Launch flow (`POST /apps/{slug}/launch`)
- `AppHubBridge` handshake
- `requestPermission`, `getUserInfo`, `sendDesktopMessage`, etc.

Publisher docs **do not** mention:

- Production, host package, `host_dev`, `installAppHubModule`
- Security architecture or isolation (that is backend / host_dev)

Human copy: **Guide → App bridge** tab.

---

## Backend security (host_dev only)

Endpoint security lives in **`audiences.host_dev.backend_security`** in JSON. Publishers do not receive this section in human-facing docs.

Host backend must:

- Enforce scopes on every `bridge/*` call
- Validate `launch_token` per app slug + session; `user_id` from `knf_core_user_id` (not client headers)
- `POST bridge/scopes` only when launch session belongs to the authenticated user
- `GET integration-docs/internal` only with `X-AppHub-Host-Access` matching `APPHUB_HOST_ACCESS_SECRET` (host integrator — **not** packages-core zone/server manager users)
- Return only zone-safe user fields packages-core allows

Publishers only see granted bridge responses — not how enforcement works.

---

## Delivery

| Channel | Location |
|---------|----------|
| HTTP (publisher) | `GET {api_prefix}/apphub/integration-docs` — filtered subset |
| HTTP (host dev) | `GET {api_prefix}/apphub/integration-docs/internal` — full JSON |
| JSON source | `packages/backend/src/Modules/Bridge/Resources/integration-docs.json` |
| Publisher (human) | Guide → **App bridge** |
| Host security | `audiences.host_dev.backend_security` (internal endpoint only) |

## Versioning

- **Patch**: doc clarifications
- **Minor**: new bridge methods or scopes
- **Major**: breaking bridge or launch contract
