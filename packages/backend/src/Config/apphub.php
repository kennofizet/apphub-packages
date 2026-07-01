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

    /** Optional enterprise host cap for iframe entry_url (non-empty = only these origins at register/launch). */
    'allowed_runtime_origins' => array_values(array_filter(array_map(
        static fn (string $v): string => trim($v),
        explode(',', (string) env('APPHUB_ALLOWED_RUNTIME_ORIGINS', '')),
    ))),

    /**
     * When enterprise list is empty: allow any HTTPS publisher origin after catalog entry_url + DEV approval.
     * Production defaults false — set APPHUB_ALLOW_ANY_PUBLISHER_RUNTIME_ORIGIN=true to opt in.
     */
    'allow_any_publisher_runtime_origin' => filter_var(
        env(
            'APPHUB_ALLOW_ANY_PUBLISHER_RUNTIME_ORIGIN',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),
        ),
        FILTER_VALIDATE_BOOL,
    ),

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

    /**
     * Optional local dev hardening — leave empty for normal use (caller IP only).
     * When set, loopback api_urls also require X-AppHub-Bridge-Proxy-Secret + publisher origin.
     */
    'bridge_proxy_secret' => trim((string) env('APPHUB_BRIDGE_PROXY_SECRET', '')),

    /** When true, loopback api_urls require APPHUB_BRIDGE_PROXY_SECRET (default off in local/testing). */
    'require_bridge_proxy_secret_on_loopback' => filter_var(
        env(
            'APPHUB_REQUIRE_BRIDGE_PROXY_SECRET',
            !in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),
        ),
        FILTER_VALIDATE_BOOL,
    ),

    /** Per-minute rate limits (per launch token hash or IP). */
    'bridge_rate_limit' => max(1, (int) env('APPHUB_BRIDGE_RATE_LIMIT', 30)),
    'bridge_user_rate_limit' => max(1, (int) env('APPHUB_BRIDGE_USER_RATE_LIMIT', 15)),
    'bridge_notify_rate_limit' => max(1, (int) env('APPHUB_BRIDGE_NOTIFY_RATE_LIMIT', 10)),
    /** Max POST bridge/notify calls per launch token until expiry. */
    'bridge_notify_max_per_token' => max(1, (int) env('APPHUB_BRIDGE_NOTIFY_MAX_PER_TOKEN', 50)),
    'verify_launch_rate_limit' => max(1, (int) env('APPHUB_VERIFY_LAUNCH_RATE_LIMIT', 20)),

    /** Max inbox rows created per POST bridge/notify (broadcast cap). */
    'notify_max_recipients' => max(1, min(1000, (int) env('APPHUB_NOTIFY_MAX_RECIPIENTS', 100))),

    /** Max JSON bytes for bridge reportError / usage metadata. */
    'usage_report_max_bytes' => max(256, (int) env('APPHUB_USAGE_REPORT_MAX_BYTES', 4096)),

    'apps_table' => env('APPHUB_APPS_TABLE', 'apphub_apps'),
    'app_versions_table' => env('APPHUB_APP_VERSIONS_TABLE', 'apphub_app_versions'),
    'app_permissions_table' => env('APPHUB_APP_PERMISSIONS_TABLE', 'apphub_app_permissions'),
    'app_bridge_consents_table' => env('APPHUB_APP_BRIDGE_CONSENTS_TABLE', 'apphub_app_bridge_consents'),
    'app_bridge_consent_intents_table' => env('APPHUB_APP_BRIDGE_CONSENT_INTENTS_TABLE', 'apphub_app_bridge_consent_intents'),
    'app_zone_access_table' => env('APPHUB_APP_ZONE_ACCESS_TABLE', 'apphub_app_zone_access'),
    'app_launch_tokens_table' => env('APPHUB_APP_LAUNCH_TOKENS_TABLE', 'apphub_app_launch_tokens'),
    'app_usage_logs_table' => env('APPHUB_APP_USAGE_LOGS_TABLE', 'apphub_app_usage_logs'),
    'user_notifications_table' => env('APPHUB_USER_NOTIFICATIONS_TABLE', 'apphub_user_notifications'),

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
    'icon_storage_root' => env('APPHUB_ICON_STORAGE_ROOT', 'apphub/icons'),

    /** Re-ping healthcheck_url when older than this (store catalog + launch). */
    'healthcheck_ttl_seconds' => max(30, (int) env('APPHUB_HEALTHCHECK_TTL_SECONDS', 300)),

    /** Max stale apps to ping per store catalog page (avoids slow first paint). */
    'healthcheck_catalog_max_per_request' => max(1, min(50, (int) env('APPHUB_HEALTHCHECK_CATALOG_MAX', 24))),

    /** Background schedule interval (minutes). Host must run `php artisan schedule:run` via cron. */
    'healthcheck_schedule_minutes' => max(1, (int) env('APPHUB_HEALTHCHECK_SCHEDULE_MINUTES', 5)),
];
