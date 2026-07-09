# Host parent-bridge examples

Copy these into your Laravel host app (`App\HubBridge\`).

| File | Purpose |
|------|---------|
| `HostParentBridgePermissionChecker.php` | RBAC gate — set `APPHUB_PARENT_BRIDGE_PERMISSION_CHECKER` |
| `ListProjectsAction.php` | Sample `project.list` handler (returns `null` until you wire your query) |

Publish config:

```bash
php artisan vendor:publish --tag=apphub-config
```

Wire real handler classes in `config/apphub-parent-bridge.php` and run `php artisan migrate` for parent consent tables.

Dev catalog: `GET /api/knf/apphub/parent-bridge/catalog` (session token).

Install UI labels for `parent.*` scopes: `GET /api/knf/apphub/parent-bridge/scope-prompts` — reads `user_prompt` from your published `config/apphub-parent-bridge.php` `scopes` block (host-defined, not fixed in Hub frontend).
