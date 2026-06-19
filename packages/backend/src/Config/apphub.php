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

    /** Allow localhost/loopback in manifest api_urls (auto on local/testing APP_ENV). */
    'allow_localhost_api_urls' => filter_var(
        env('APPHUB_ALLOW_LOCALHOST_API_URLS', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
        FILTER_VALIDATE_BOOL,
    ),

    /** Comma-separated origins allowed for iframe entry_url (e.g. https://apps.example.com). Required in production. */
    'allowed_runtime_origins' => array_values(array_filter(array_map(
        static fn (string $v): string => trim($v),
        explode(',', (string) env(
            'APPHUB_ALLOWED_RUNTIME_ORIGINS',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
                ? 'http://localhost:5180,http://127.0.0.1:5180,http://localhost:15180,http://127.0.0.1:15180'
                : '',
        )),
    ))),

    /** Hub SPA origins allowed to embed hosted runtime (frame-ancestors CSP). */
    'allowed_hub_origins' => array_values(array_filter(array_map(
        static fn (string $v): string => trim($v),
        explode(',', (string) env(
            'APPHUB_ALLOWED_HUB_ORIGINS',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
                ? 'http://localhost:5173,http://127.0.0.1:5173'
                : '',
        )),
    ))),

    /** Product shell origins when Hub is embedded (nested iframe: product → hub → app). See hub-host-starter README. */
    'allowed_product_origins' => array_values(array_filter(array_map(
        static fn (string $v): string => trim($v),
        explode(',', (string) env(
            'APPHUB_ALLOWED_PRODUCT_ORIGINS',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
                ? 'http://localhost:3000,http://127.0.0.1:3000'
                : '',
        )),
    ))),

    /** Seconds before install intent expires (shown in permission dialog). */
    'install_intent_ttl_seconds' => max(30, (int) env('APPHUB_INSTALL_INTENT_TTL_SECONDS', 120)),

    /** When true, bridge HTTP callers must match DNS-pinned IPs stored on manifest at publish. */
    'use_api_url_ip_pins' => filter_var(
        env('APPHUB_USE_API_URL_IP_PINS', !in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
        FILTER_VALIDATE_BOOL,
    ),

    'apps_table' => env('APPHUB_APPS_TABLE', 'apphub_apps'),
    'app_versions_table' => env('APPHUB_APP_VERSIONS_TABLE', 'apphub_app_versions'),
    'app_permissions_table' => env('APPHUB_APP_PERMISSIONS_TABLE', 'apphub_app_permissions'),
    'app_bridge_consents_table' => env('APPHUB_APP_BRIDGE_CONSENTS_TABLE', 'apphub_app_bridge_consents'),
    'app_bridge_consent_intents_table' => env('APPHUB_APP_BRIDGE_CONSENT_INTENTS_TABLE', 'apphub_app_bridge_consent_intents'),
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
