# App Hub host starter (Vue 3)

> ## ⚠️ IMPORTANT — read this first
>
> **This folder is a separate Vue app for your App Hub subdomain — not for your main product site.**
>
> | Do | Don't |
> |----|-------|
> | Clone or download **this** folder | Put `installAppHubModule()` inside your main product Vue app |
> | Deploy to a **dedicated origin** (e.g. `https://apphub.yourcompany.com`) | Run Hub on the same origin as your product SPA |
> | Embed that URL in an **iframe** from your product | Mount `<AppHubDesktop />` in your product layout |
>
> **Why:** App Hub must stay on its own origin so hosted publisher apps cannot read your product's `localStorage` / cookies. Your Laravel backend (`apphub-backend`) stays on the API host; this starter is only the Hub **desktop UI**.
>
> **Typical setup**
> 1. Configure `.env` → `npm run build` → deploy `dist/` to `https://apphub.yourcompany.com`
> 2. Product app embeds `<iframe src="https://apphub.yourcompany.com">`
> 3. Parent sends `postMessage` with user token, language, and theme (see below)
>
> Dependencies: [`@kennofizet/apphub-frontend`](https://www.npmjs.com/package/@kennofizet/apphub-frontend) (`latest` in `package.json` — run `npm install @kennofizet/apphub-frontend@latest` to upgrade).

---

Minimal **Vue 3 + Vite** shell that installs `@kennofizet/apphub-frontend` and renders the Windows-style Hub desktop.

## Requirements

- Node.js 18+
- Laravel with `kennofizet/apphub-backend` installed and migrated
- packages-core **user token** per session (from your product login)

## Setup

```bash
cp .env.example .env
# Edit .env — your public API URLs and parent origins
npm install
npm run build
```

Deploy the `dist/` folder to your Hub subdomain.

## Environment (`.env` — CI/CD at build time)

Public API URLs only. **Not** user token or theme — parent sends those via `postMessage`.

| Variable | Required | Description |
|----------|----------|-------------|
| `VITE_APPHUB_BACKEND_URL` | yes | App Hub API base, e.g. `https://api.yourcompany.com/api/knf/apphub` |
| `VITE_APPHUB_CORE_URL` | no | packages-core API base (zones) |
| `VITE_APPHUB_PARENT_ORIGINS` | recommended | Comma-separated product origins allowed to `postMessage` |
| `VITE_APPHUB_HOST_ACCESS_SECRET` | no | Host integrator bridge docs secret |

## Parent → iframe (`postMessage`)

Production flow: parent product owns token, language, and theme. Hub waits until config arrives.

**Hub → parent (on load)**

```js
{ channel: 'apphub:host', type: 'ready' }
```

**Parent → Hub (on load + when user changes settings)**

```js
iframe.contentWindow.postMessage(
  {
    channel: 'apphub:host',
    type: 'config',
    token: userSessionToken,
    language: 'vi',
    theme: 'dark',
    themeToggle: false,
    // Required when product embeds Hub in an iframe — hosted apps need this for CSP frame-ancestors
    productOrigin: window.location.origin,
  },
  'https://apphub.yourcompany.com',
)
```

**Parent example (Vue)**

```js
const hubOrigin = 'https://apphub.yourcompany.com'
const hubRef = ref(null)

function pushHubConfig() {
  hubRef.value?.contentWindow?.postMessage(
    {
      channel: 'apphub:host',
      type: 'config',
      token: authStore.token,
      language: settings.locale,
      theme: settings.isDark ? 'dark' : 'light',
      themeToggle: false,
      productOrigin: window.location.origin,
    },
    hubOrigin,
  )
}

watch(
  () => [authStore.token, settings.locale, settings.isDark],
  () => pushHubConfig(),
)

// After iframe load, listen for ready then push:
window.addEventListener('message', (e) => {
  if (e.origin !== hubOrigin) return
  if (e.data?.channel === 'apphub:host' && e.data?.type === 'ready') pushHubConfig()
})
```

## iframe (main product)

```html
<iframe
  id="apphub-frame"
  title="App Hub"
  src="https://apphub.yourcompany.com"
  style="width:100%;height:100%;border:0"
  allow="clipboard-read; clipboard-write"
></iframe>
```

Open app by slug: `src="https://apphub.yourcompany.com?open=my-app-slug"` (token still via `postMessage`).

## Local dev — open Hub host directly (no iframe)

When running `npm run dev`, you can open the Hub URL directly (e.g. `http://localhost:5173`) without the product iframe. Vite dev mode enables this automatically.

Add to `.env`:

```env
VITE_APPHUB_DEV_LOGIN_URL=http://127.0.0.1:8000/api/user/login?user_id=1
```

Or pass a token once: `http://localhost:5173/?token=YOUR_SESSION_TOKEN`

Production builds do **not** auto-login — use the product iframe + `postMessage` flow.

## CORS (Laravel)

Hub SPA and API are different origins. Add your Hub URL to Laravel `config/cors.php`:

```php
'allowed_origins' => [
    'https://apphub.yourcompany.com',
    'https://app.yourcompany.com',
],
```

Set `APP_URL` on Laravel to your API host; bootstrap derives Hub/runtime origins.

## Hosted apps — nested iframe (`frame-ancestors`)

When users open a **hosted** app (`runtime_type: hosted`), the bundle is served from the API and shown in a **third** iframe:

```text
Product (app.yourcompany.com)
  └── Hub iframe (apphub.yourcompany.com)
        └── Hosted app iframe (api…/apphub/apps/{slug}/runtime/…)
```

Browsers enforce CSP **`frame-ancestors`** on the hosted bundle. **Every ancestor origin** in that chain must be allowed, or the app window stays blank (launch succeeds; no CSS/JS loads).

| Laravel `.env` | Who | Example |
|----------------|-----|---------|
| `APPHUB_ALLOWED_HUB_ORIGINS` | Hub SPA origin | `https://apphub.yourcompany.com` |
| `APPHUB_ALLOWED_PRODUCT_ORIGINS` | Product that embeds Hub | `https://app.yourcompany.com` |

**Parent must send `productOrigin`** in the `postMessage` config (see above). Hub forwards it on the runtime launch URL as `product_origin` (and `hub_origin` for the Hub page).

**Local dev defaults** (`APP_ENV=local`): Hub `http://localhost:5173`, product `http://localhost:3000`. If Hub runs on another Vite port (e.g. `5174` because `5173` is taken), add it to `APPHUB_ALLOWED_HUB_ORIGINS`.

```env
APPHUB_ALLOWED_HUB_ORIGINS=https://apphub.yourcompany.com
APPHUB_ALLOWED_PRODUCT_ORIGINS=https://app.yourcompany.com
```

Loopback origins are also accepted automatically when `APPHUB_ALLOW_LOCALHOST_API_URLS` is true (default in local/testing).

## Package upgrade

```bash
npm install @kennofizet/apphub-frontend@latest
npm run build
```

Pin a version in `package.json` (e.g. `"^0.2.0"`) for reproducible CI builds.

## Project layout

```
hub-host-starter/
├── src/
│   ├── main.js
│   ├── App.vue
│   ├── apphub-config.js
│   └── useParentHostConfig.js
├── index.html
├── vite.config.js
├── package.json
└── .env.example
```
