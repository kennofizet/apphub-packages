<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Support\Facades\Http;
use Kennofizet\AppHub\Modules\Catalog\Models\App;

final class AppHealthcheckService
{
    /**
     * @return array{
     *     slug: string,
     *     ok: bool,
     *     status: int|null,
     *     latency_ms: int|null,
     *     healthcheck_url: string|null,
     *     error: string|null
     * }
     */
    public function ping(App $app): array
    {
        $url = trim((string) ($app->healthcheck_url ?? ''));
        if ($url === '') {
            return [
                'slug' => $app->slug,
                'ok' => false,
                'status' => null,
                'latency_ms' => null,
                'healthcheck_url' => null,
                'error' => 'no_healthcheck_url',
            ];
        }

        $started = microtime(true);

        try {
            $response = Http::timeout(10)->acceptJson()->get($url);
            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'slug' => $app->slug,
                'ok' => $response->successful(),
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
                'healthcheck_url' => $url,
                'error' => $response->successful() ? null : 'unhealthy',
            ];
        } catch (\Throwable) {
            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'slug' => $app->slug,
                'ok' => false,
                'status' => null,
                'latency_ms' => $latencyMs,
                'healthcheck_url' => $url,
                'error' => 'request_failed',
            ];
        }
    }
}
