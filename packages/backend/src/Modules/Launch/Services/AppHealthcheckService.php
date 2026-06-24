<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;

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
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->acceptJson()
                ->get($url);
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

    /** @return Collection<int, App> */
    public function activeAppsWithHealthUrl(): Collection
    {
        return App::query()
            ->where('status', AppStatus::ACTIVE)
            ->whereNotNull('healthcheck_url')
            ->where('healthcheck_url', '!=', '')
            ->orderBy('slug')
            ->get();
    }

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
    public function pingAndPersist(App $app): array
    {
        $result = $this->ping($app);
        $app->health_ok = $result['ok'];
        $app->health_checked_at = now();
        $app->save();

        return $result;
    }

    public function ttlSeconds(): int
    {
        return max(30, (int) config('apphub.healthcheck_ttl_seconds', 300));
    }

    public function isStale(App $app): bool
    {
        $url = trim((string) ($app->healthcheck_url ?? ''));
        if ($url === '' || (string) $app->status !== AppStatus::ACTIVE) {
            return false;
        }

        $checkedAt = $app->health_checked_at;
        if ($checkedAt === null) {
            return true;
        }

        return $checkedAt->lt(now()->subSeconds($this->ttlSeconds()));
    }

    /**
     * Ping apps whose health_checked_at is missing or older than TTL.
     *
     * @param iterable<int, App> $apps
     */
    public function refreshStaleApps(iterable $apps, ?int $max = null): int
    {
        $limit = $max ?? max(1, (int) config('apphub.healthcheck_catalog_max_per_request', 24));
        $refreshed = 0;

        foreach ($apps as $app) {
            if (!$app instanceof App || !$this->isStale($app)) {
                continue;
            }

            $this->pingAndPersist($app);
            $refreshed++;

            if ($refreshed >= $limit) {
                break;
            }
        }

        return $refreshed;
    }
}
