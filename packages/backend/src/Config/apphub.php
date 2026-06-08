<?php declare(strict_types=1);

return [
    'api_prefix' => env('APPHUB_API_PREFIX', 'apphub'),
    'catalog_per_page' => (int) env('APPHUB_CATALOG_PER_PAGE', 24),
    'launch_token_ttl' => (int) env('APPHUB_LAUNCH_TOKEN_TTL', 900),
    /** Host integrator only — not packages-core zone/server managers. Pass as hostAccessSecret in installAppHubModule. */
    'host_access_secret' => env('APPHUB_HOST_ACCESS_SECRET', ''),
];
