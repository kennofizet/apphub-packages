<?php declare(strict_types=1);

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Stubs\NullArrayParentBridgeAction;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Stubs\NullObjectParentBridgeAction;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Stubs\VoidParentBridgeEventListener;

return [
    'enabled' => filter_var(env('APPHUB_PARENT_BRIDGE_ENABLED', true), FILTER_VALIDATE_BOOL),

    'defaults' => [
        'timeout_ms' => max(1000, (int) env('APPHUB_PARENT_BRIDGE_TIMEOUT_MS', 30_000)),
        'max_args_bytes' => max(1024, (int) env('APPHUB_PARENT_BRIDGE_MAX_ARGS_BYTES', 65_536)),
        'rate_limit_per_minute' => max(1, (int) env('APPHUB_PARENT_BRIDGE_RATE_LIMIT', 60)),
        /**
         * Default demo payloads for publisher testing (draft / pending version before DEV approves parent scopes).
         * Key = action name. Override per action with action.demo_data when needed.
         *
         * @var array<string, mixed>
         */
        'demo_data' => [
            'project.list' => [
                [
                    'id' => 9001,
                    'code' => 'DEMO-A',
                    'name' => 'Demo project A',
                    'status' => 'active',
                    'updated_at' => '2026-07-01T00:00:00Z',
                ],
                [
                    'id' => 9002,
                    'code' => 'DEMO-B',
                    'name' => 'Demo project B',
                    'status' => 'planning',
                    'updated_at' => '2026-07-02T00:00:00Z',
                ],
            ],
            'project.members' => [
                [
                    'userId' => 1,
                    'name' => 'Demo Lead',
                    'role' => 'lead',
                    'jobPosition' => 'Project Manager',
                ],
                [
                    'userId' => 2,
                    'name' => 'Demo Member',
                    'role' => 'member',
                    'jobPosition' => 'Developer',
                ],
                [
                    'userId' => 3,
                    'name' => 'Demo Reviewer',
                    'role' => 'reviewer',
                    'jobPosition' => 'QA Engineer',
                ],
            ],
            'signature.user' => [
                'url' => null,
                'mime' => 'image/png',
                'data' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            ],
        ],
    ],

    /**
     * Optional FQCN implementing ParentBridgePermissionChecker (host RBAC).
     * Example: App\HubBridge\HostParentBridgePermissionChecker
     */
    'permission_checker' => env('APPHUB_PARENT_BRIDGE_PERMISSION_CHECKER', ''),

    'security' => [
        'require_active_session' => env('APPHUB_PARENT_BRIDGE_REQUIRE_SESSION'),
        'require_permission_in_production' => env('APPHUB_PARENT_BRIDGE_REQUIRE_PERMISSION'),
        'audit_log' => filter_var(env('APPHUB_PARENT_BRIDGE_AUDIT_LOG', false), FILTER_VALIDATE_BOOL),
        'audit_channel' => env('APPHUB_PARENT_BRIDGE_AUDIT_CHANNEL', 'stack'),
        'allowed_handler_namespaces' => [
            'Kennofizet\\AppHub\\',
            'App\\HubBridge\\',
        ],
    ],

    /**
     * Optional extra install-consent scope metadata (merged with parent.* pattern validation).
     *
     * @var array<string, array{description?: string, user_prompt?: string}>
     */
    'scopes' => [
        'parent.project.list' => [
            'description' => 'Read project list from parent production suite.',
            'user_prompt' => 'Allow {app} to read projects from your workspace?',
        ],
        'parent.project.members' => [
            'description' => 'Read project team members from parent production suite.',
            'user_prompt' => 'Allow {app} to read project team members?',
        ],
        'parent.signature.user' => [
            'description' => 'Read user signature image from parent production suite.',
            'user_prompt' => 'Allow {app} to read user signatures?',
        ],
        'parent.events' => [
            'description' => 'Send events to the parent production suite.',
            'user_prompt' => 'Allow {app} to notify the parent app?',
        ],
    ],

    /**
     * RPC actions — child SDK callParent(action, args).
     * Replace handler classes with your host app implementations.
     *
     * @var array<string, array<string, mixed>>
     */
    'actions' => [
        'project.list' => [
            'handler' => NullArrayParentBridgeAction::class,
            'permission' => null,
            'bridge_scope' => 'parent.project.list',
            'mode' => 'query',
            'args' => [
                'query' => [
                    'type' => 'pagination_search',
                    'optional' => true,
                    'max_per_page' => 100,
                ],
            ],
            'returns' => [
                'type' => 'array',
                'nullable' => true,
                'items' => ['id', 'code', 'name', 'status', 'updated_at'],
            ],
        ],

        'project.members' => [
            'handler' => NullArrayParentBridgeAction::class,
            'permission' => null,
            'bridge_scope' => 'parent.project.members',
            'mode' => 'query',
            'args' => [
                'projectCode' => ['type' => 'string', 'required' => true, 'max' => 64],
                'query' => ['type' => 'pagination_search', 'optional' => true],
            ],
            'returns' => [
                'type' => 'array',
                'nullable' => true,
                'items' => ['userId', 'name', 'role', 'jobPosition'],
            ],
        ],

        'signature.user' => [
            'handler' => NullObjectParentBridgeAction::class,
            'permission' => null,
            'bridge_scope' => 'parent.signature.user',
            'mode' => 'read',
            'args' => [
                'userId' => ['type' => 'integer', 'required' => true, 'min' => 1],
                'jobPosition' => ['type' => 'string', 'optional' => true, 'max' => 128],
            ],
            'returns' => [
                'type' => 'object',
                'nullable' => true,
                'fields' => ['url', 'mime', 'data'],
            ],
        ],
    ],

    /**
     * Fire-and-forget events — child SDK emitToParent(name, payload).
     *
     * @var array<string, array<string, mixed>>
     */
    'events' => [
        'bonus.assign' => [
            'listener' => VoidParentBridgeEventListener::class,
            'permission' => null,
            'bridge_scope' => 'parent.events',
            'payload' => [
                'projectCode' => ['type' => 'string', 'required' => true, 'max' => 64],
                'userId' => ['type' => 'integer', 'required' => true, 'min' => 1],
            ],
            'rate_limit_per_minute' => 30,
        ],
    ],
];
