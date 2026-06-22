<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Console;

use Illuminate\Console\Command;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckService;

final class HealthcheckAppsCommand extends Command
{
    protected $signature = 'apphub:healthcheck {--slug= : Ping a single app slug}';

    protected $description = 'Ping healthcheck_url for active apps and store health_ok on the catalog row';

    public function handle(AppHealthcheckService $healthcheck, AppCatalogService $catalog): int
    {
        $slug = trim((string) $this->option('slug'));

        if ($slug !== '') {
            $app = $catalog->findBySlug($slug);
            if ($app === null) {
                $this->error("App not found: {$slug}");

                return self::FAILURE;
            }

            $result = $healthcheck->pingAndPersist($app);
            $this->line($this->formatLine($result));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $apps = $healthcheck->activeAppsWithHealthUrl();
        if ($apps->isEmpty()) {
            $this->info('No active apps with healthcheck_url.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($apps as $app) {
            $result = $healthcheck->pingAndPersist($app);
            $this->line($this->formatLine($result));
            if ($result['ok']) {
                $ok++;
            } else {
                $failed++;
            }
        }

        $this->info("Done: {$ok} healthy, {$failed} unhealthy.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array{slug: string, ok: bool, status: int|null, latency_ms: int|null, error: string|null} $result
     */
    private function formatLine(array $result): string
    {
        $status = $result['status'] ?? '—';
        $latency = $result['latency_ms'] ?? '—';
        $flag = $result['ok'] ? 'OK' : 'FAIL';
        $error = $result['error'] ? " ({$result['error']})" : '';

        return sprintf(
            '[%s] %s status=%s latency=%sms%s',
            $flag,
            $result['slug'],
            $status,
            $latency,
            $error,
        );
    }
}
