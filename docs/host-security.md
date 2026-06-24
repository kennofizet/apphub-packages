# App Hub host security checklist

For **host platform** teams integrating `apphub-backend` + `apphub-frontend`. Publishers do not need this document.

---

## Trusted proxies and client IP

Bridge HTTP (`GET bridge/user`, `POST bridge/notify`) validates the **TCP client IP** against manifest `api_urls` (DNS A/AAAA or optional IP pins).

If the Hub runs behind a reverse proxy or load balancer, Laravel must trust that proxy so `$request->ip()` is the real caller, not the proxy hop.

**Host app responsibilities:**

1. Configure Laravel `TrustProxies` (or equivalent) for your CDN / ingress / nginx.
2. Only trust headers (`X-Forwarded-For`, `Forwarded`) from infrastructure you control.
3. In production, set `APPHUB_USE_API_URL_IP_PINS=true` (default outside `local`/`testing`) so published manifests store DNS pins at publish time.

Misconfigured proxies let attackers spoof `api_urls` checks by forging `X-Forwarded-For`.

---

## Production environment variables

| Variable | Recommended production |
|----------|------------------------|
| `APPHUB_ALLOW_LOCALHOST_API_URLS` | `false` |
| `APPHUB_USE_API_URL_IP_PINS` | `true` |
| `APPHUB_BRIDGE_PROXY_SECRET` | empty (not used in prod); set only for hardened local dev |
| `APPHUB_BRIDGE_RATE_LIMIT` | `30` (per token / minute) |
| `APPHUB_BRIDGE_USER_RATE_LIMIT` | `15` (per token / minute) |
| `APPHUB_BRIDGE_NOTIFY_RATE_LIMIT` | `10` (per token / minute) |
| `APPHUB_BRIDGE_NOTIFY_MAX_PER_TOKEN` | `50` (per launch session) |
| `APPHUB_REQUIRE_BRIDGE_PROXY_SECRET` | `true` in production/staging when loopback `api_urls` allowed |
| `APPHUB_NOTIFY_MAX_RECIPIENTS` | `100` |
| `APPHUB_ALLOWED_HUB_ORIGINS` | your Hub SPA origin(s) |
| `APPHUB_ALLOWED_RUNTIME_ORIGINS` | optional enterprise cap for iframe `entry_url` |

---

## Launch tokens

- Minted server-side on `POST /apps/{slug}/launch`; scopes come from install consent DB only.
- **Multi-use** for runtime assets, `bridge/user`, and `bridge/notify` until TTL — rate-limited per token hash; notify also capped per session (`APPHUB_BRIDGE_NOTIFY_MAX_PER_TOKEN`).
- **One-shot** only on `POST verify-launch-token` (publisher tool backend).
- Bridge HTTP requires matching `X-AppHub-Session-Id` when the launch token has a `session_id` (sent on `bridge:ready`).
- Tokens appear in hosted runtime URLs and `apphub:bridge:ready` — treat as session secrets; use short TTL (`APPHUB_LAUNCH_TOKEN_TTL`, max 180s).

Do **not** bind bridge calls to the user’s mint IP: the caller is the publisher server / proxy, not the Hub browser session.

---

## Notify fan-out

`POST bridge/notify` defaults to the **launching user** only. Org-wide delivery requires `broadcast: true`. Targeted delivery uses `user_ids[]` (max 50). Recipients must have installed the app and consented `desktop.notify`. Draft apps cannot notify outside `local`/`testing`.

---

## Frontend embed flags

`allowUnsafeOrigin` and `allowSameOriginEmbed` on `installAppHubModule()` are **ignored** unless the Hub page is on localhost or the user is in `APPHUB_DEV_USER_IDS`. Do not rely on them in production.

---

## Healthcheck URLs

`healthcheck_url` is validated at publish (no private IPs, metadata hosts, or redirects on ping). It is redacted from public store catalog responses.

---

## Internal docs

Full endpoint matrix: `packages/backend/src/Modules/Bridge/Resources/integration-docs.json` → `audiences.host_dev.backend_security` (requires `X-AppHub-Host-Access`).
