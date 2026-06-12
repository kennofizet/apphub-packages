<?php declare(strict_types=1);

return [
    'api_prefix' => env('APPHUB_API_PREFIX', 'apphub'),
    /** Default page size for GET /apps and GET dev/apps (max 50 — packages-core validator). */
    'catalog_per_page' => min(50, max(1, (int) env('APPHUB_CATALOG_PER_PAGE', 24))),
    'launch_token_ttl' => (int) env('APPHUB_LAUNCH_TOKEN_TTL', 180),
    'launch_token_ttl_min' => 60,
    'launch_token_ttl_max' => 180,

    /** Host integrator only — not packages-core zone/server managers. */
    'host_access_secret' => env('APPHUB_HOST_ACCESS_SECRET', ''),

    'apps_table' => env('APPHUB_APPS_TABLE', 'apphub_apps'),
    'app_versions_table' => env('APPHUB_APP_VERSIONS_TABLE', 'apphub_app_versions'),
    'app_permissions_table' => env('APPHUB_APP_PERMISSIONS_TABLE', 'apphub_app_permissions'),
    'app_zone_access_table' => env('APPHUB_APP_ZONE_ACCESS_TABLE', 'apphub_app_zone_access'),
    'app_launch_tokens_table' => env('APPHUB_APP_LAUNCH_TOKENS_TABLE', 'apphub_app_launch_tokens'),
    'app_usage_logs_table' => env('APPHUB_APP_USAGE_LOGS_TABLE', 'apphub_app_usage_logs'),

    'rbac' => [
        'driver' => env('APPHUB_RBAC_DRIVER', 'zone'),
        'role_column' => env('APPHUB_RBAC_ROLE_COLUMN'),
        'department_column' => env('APPHUB_RBAC_DEPARTMENT_COLUMN'),
    ],

    'dev_user_ids' => array_values(array_filter(array_map(
        static fn ($v) => (int) trim((string) $v),
        explode(',', (string) env('APPHUB_DEV_USER_IDS', '')),
    ))),

    'bundle_disk' => env('APPHUB_BUNDLE_DISK', 'local'),
    'bundle_max_bytes' => (int) env('APPHUB_BUNDLE_MAX_BYTES', 52_428_800),
    'bundle_storage_root' => env('APPHUB_BUNDLE_STORAGE_ROOT', 'apphub/bundles'),
];
