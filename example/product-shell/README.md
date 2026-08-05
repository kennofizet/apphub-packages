# Product shell — parent bridge listener

When your product embeds App Hub in an iframe, child publisher apps can call `callParent(action, args)` to reach **your** production backend through this listener.

## Install in product app (parent window)

```js
import { installAppHubProductBridgeListener } from './product-bridge-listener.js'

const uninstall = installAppHubProductBridgeListener({
  hubOrigin: 'https://apphub.yourcompany.com',
  backendUrl: 'https://api.yourcompany.com/api/knf/apphub',
  token: userSessionToken,
  getToken: () => currentUserToken,
  onAppEvent(detail) {
    console.log('Child app event', detail.name, detail.payload)
  },
})
```

## Configure handlers (Laravel host)

1. Publish config: `php artisan vendor:publish --tag=apphub-config`
2. Edit `config/apphub-parent-bridge.php` — set `handler` / `listener` classes, `permission`, `bridge_scope`, and publisher-facing `args` / `returns` per action (these auto-appear on `GET …/integration-docs` as `host_action_contracts`)
3. Production: bind `APPHUB_PARENT_BRIDGE_PERMISSION_CHECKER` to a class that loads your `App\Models\User` and calls `hubBridgeCan()` — see [example/hub-bridge/HostParentBridgePermissionChecker.php](../hub-bridge/HostParentBridgePermissionChecker.php)
4. Set `APPHUB_ALLOWED_PRODUCT_ORIGINS` so Hub only relays when `productOrigin` matches your product SPA
5. Enable `APPHUB_PARENT_BRIDGE_REQUIRE_SESSION=true` in production so API calls require an active launch session

Default stub handlers return `null` until you wire real production services. Stub actions with `permission: null` are **rejected in production** until you assign real permissions.

## Security

The API enforces a triple gate before running any handler:

1. **Manifest** — action/event must be declared in the child app's `parent_bridge` block
2. **Install consent** — user must have granted the matching `parent.*` scope
3. **Config** — `bridge_scope` must match; handler class must be in allowed namespaces; optional RBAC via `permission`

Hub additionally validates `productOrigin` against `allowed_product_origins` from bootstrap, caps payload size, and forwards `app_slug`, `bridge_scope`, and `session_id` (not `display_user`).

## Flow

```text
Child app → Hub (apphub:bridge) → Product shell (apphub:product) → POST /parent-bridge/call → your handler class
```

Configure actions in host Laravel `config/apphub-parent-bridge.php` (`handler`, `permission`, `bridge_scope`, `args`, `returns` per action).
