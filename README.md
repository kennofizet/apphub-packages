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
| [docs/manifest-schema.md](./docs/manifest-schema.md) | `manifest.json` field reference (hosted + iframe) |
| [docs/smoke-test.md](./docs/smoke-test.md) | Manual smoke checklist before release |
| [packages/backend/src/Modules/Bridge/Resources/integration-docs.json](./packages/backend/src/Modules/Bridge/Resources/integration-docs.json) | Source of truth JSON |

Publisher docs explain **how to use** the bridge. Endpoint security is documented under **host_dev** only.

## Packages

| Package | README | Status |
|---------|--------|--------|
| Frontend | [packages/frontend/README.md](./packages/frontend/README.md) | Vue 3 desktop shell, App Store, runner (hosted + iframe) |
| Backend | [packages/backend/README.md](./packages/backend/README.md) | Laravel API — catalog, launch, bridge, hosted runtime serve |

## Hub host starter (download & deploy)

> **⚠️ For integrators:** use the **hub-host-starter** Vue app for your **App Hub subdomain**. Deploy to e.g. `apphub.yourcompany.com` and embed it in your main product **iframe**. Do **not** install the Hub module inside your product SPA.

**Clone (standalone repo — recommended for clients):**

```bash
git clone https://github.com/kennofizet/apphub-host-starter.git
cd apphub-host-starter
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

Publish `config/apphub-parent-bridge.php` and fill `scopes[]` + `actions[]` (`handler`, `bridge_scope`, `args`, `returns`).  
`GET …/integration-docs` auto-exposes:

- live `parent_scopes.scopes` from host `scopes[]`
- live `parent_bridge.host_action_contracts.actions` from host `actions[].args/returns`  
  (no `IntegrationDocsController` override needed for the common case)

```php
// config/apphub-parent-bridge.php
'actions' => [
    'project.list' => [
        'handler' => App\HubBridge\ProjectListAction::class,
        'bridge_scope' => 'parent.project.list',
        'mode' => 'read',
        'args' => [ /* ... */ ],
        'returns' => [ /* ... */ ],
    ],
],
// → visible at GET …/integration-docs
//   audiences.publisher.bridge.parent_bridge.host_action_contracts.actions['project.list']
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

- **Publisher** (app in Hub window): `GET …/integration-docs` → `audiences.publisher.bridge` (+ live host action contracts)
- **Host dev** (embed package): README + `audiences.host_dev`
