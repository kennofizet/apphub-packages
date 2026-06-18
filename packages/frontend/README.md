# App Hub Frontend

Vue 3 **Windows-style desktop** for App Hub. Modular apps run in draggable windows.

## Modules (independent)

| Module | Path | Role |
|--------|------|------|
| **desktop** | `src/modules/desktop` | Wallpaper, icons, taskbar, Start menu |
| **window-manager** | `src/modules/window-manager` | Open / close / focus / minimize windows |
| **app-store** | `src/modules/app-store` | Built-in **App Store** (default Hub app) |

Each module has its own `index.js` and can be imported alone:

```js
import { AppHubDesktop } from '@kennofizet/apphub-frontend/modules/desktop'
import { useWindowManager } from '@kennofizet/apphub-frontend/modules/window-manager'
```

## Install (host app)

```bash
npm install @kennofizet/apphub-frontend
```

```js
import { createApp } from 'vue'
import { installAppHubModule, AppHubDesktop } from '@kennofizet/apphub-frontend'

const app = createApp(App)
const hostApi = installAppHubModule(app, {
  coreUrl: 'https://your-api/api/knf',
  backendUrl: 'https://your-api/api/knf/apphub',
  token: sessionTokenFromHost,
  hostAccessSecret: import.meta.env.VITE_APPHUB_HOST_ACCESS_SECRET,
  language: 'vi',
  theme: 'dark',
  themeToggle: false,
  allowedRuntimeOrigins: ['https://publisher-app.example.com'],
})
```

**Local dev:** `backendUrl` + `token` is enough on `localhost` — the package auto-relaxes origin checks.

**Production:** Bootstrap auto-derives URLs from existing Laravel env (no extra App Hub vars):

- Hub host → browser `Origin` on `GET /bootstrap` (where the Vue app runs)
- Runtime API → `{APP_URL}/{KNF_CORE_API_PREFIX}/{APPHUB_API_PREFIX}` (Laravel backend)

Set `APP_URL` to your Laravel API host. Hub SPA can be on a different origin (e.g. Vite `:3000` in dev, `apphub.` subdomain in prod) — bootstrap learns it from the request. Optional overrides: `hubOrigin`, `runtimePublicUrl`. Embed the Hub URL in your product iframe — do not mount Hub inside the product Vue app.

**Hosted apps in nested iframe:** When using `hub-host-starter`, parent must send `productOrigin` in `postMessage` config. Laravel needs `APPHUB_ALLOWED_HUB_ORIGINS` and `APPHUB_ALLOWED_PRODUCT_ORIGINS` so hosted bundle CSP `frame-ancestors` allows both Hub and product. See [hub-host-starter/README.md](../../hub-host-starter/README.md).

`hostApi.integrationDocsInternal` — host app only, not `inject()`.

Hub shell components use `useAppHubHostApi()` from the package. The host token and `hostAccessSecret` stay in private module credentials — not in `inject('apphubOptions')` and not via `provide('apphubApi')`.

### Who can read internal docs?

| Role | Internal docs (`integration-docs/internal`)? |
|------|---------------------------------------------|
| End user on Hub desktop | No — use Guide or public `integration-docs` |
| Publisher | No |
| packages-core **zone/server manager** (settings in other packages) | **No** — that is not host integrator |
| **Host team** embedding App Hub | Yes — `APPHUB_HOST_ACCESS_SECRET` + `hostAccessSecret` in `installAppHubModule` |

Do not pass `hostAccessSecret` to every logged-in user. Only your host app build/config (dev/ops), never user login API.

```vue
<template>
  <AppHubDesktop language="vi" theme="light" :theme-toggle="false" />
</template>
```

Full-screen route example: `/apphub` → only `<AppHubDesktop />`.

## UX

- Desktop icons (double-click to open).
- **App Store** icon is always on the desktop (install user apps).
- On first load, App Store window opens automatically (`openAppStoreOnMount`, default `true`).
- Installed apps from the store appear as new desktop icons (demo placeholder window until backend launch).

## Verify

Use `____TEST/test` frontend (manual `npm install` there). Do not run `npm install` inside `apphub-packages` per project rules.
