# App Hub Packages

Monorepo for **apphub-backend** (Laravel) and **apphub-frontend** (Vue 3).

**App Hub** is a host + marketplace layer: end users discover and run apps; publishers connect apps via the **App Hub bridge** (permissions, user info, desktop messages).

## Documentation

| Audience | Document |
|----------|----------|
| **Publisher** | Guide → **App bridge** + `integration-docs.json` → `audiences.publisher.bridge` (usage only) |
| **Host dev** | `audiences.host_dev.backend_security` + [packages/frontend/README.md](./packages/frontend/README.md) |
| **End user** | Guide → **For users** |

| File | Description |
|------|-------------|
| [docs/integration-docs.md](./docs/integration-docs.md) | Contract overview |
| [packages/backend/src/Modules/Bridge/Resources/integration-docs.json](./packages/backend/src/Modules/Bridge/Resources/integration-docs.json) | Source of truth JSON |

Publisher docs explain **how to use** the bridge. Endpoint security is documented under **host_dev** only.

## Packages

| Package | README | Status |
|---------|--------|--------|
| Frontend | [packages/frontend/README.md](./packages/frontend/README.md) | Windows desktop shell + App Store module |
| Backend | `packages/backend` | Not started (minimal rebuild) |

## Hub host starter (download & deploy)

> **⚠️ For integrators:** use the **hub-host-starter** Vue app for your **App Hub subdomain**. Deploy to e.g. `apphub.yourcompany.com` and embed it in your main product **iframe**. Do **not** install the Hub module inside your product SPA.

**Clone (standalone repo — recommended for clients):**

```bash
git clone https://github.com/kennofizet/apphub-hub-host-starter.git
cd apphub-hub-host-starter
cp .env.example .env
npm install
npm run build
```

Full setup (`.env`, iframe `postMessage`, CORS): [hub-host-starter/README.md](./hub-host-starter/README.md).

## Quick start (host app — install in `____TEST/test`, not this repo)

**Backend**

```bash
composer require kennofizet/apphub-backend
php artisan vendor:publish --tag=apphub-config
php artisan vendor:publish --tag=apphub-migrations
php artisan migrate
```

**Frontend**

```bash
npm install @kennofizet/apphub-frontend
```

```js
installAppHubModule(app, {
  coreUrl: 'https://your-api/api/knf',
  backendUrl: 'https://your-api/api/knf/apphub',
  token: '...',
})
```

**Docs by audience**

- **Publisher** (app in Hub window): `GET …/integration-docs` → `audiences.publisher.bridge`
- **Host dev** (embed package): README + `audiences.host_dev`
