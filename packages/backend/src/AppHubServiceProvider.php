<?php declare(strict_types=1);

namespace Kennofizet\AppHub;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Kennofizet\AppHub\Modules\Bridge\Http\Middleware\EnsureAppHubHostAccess;
use Kennofizet\AppHub\Modules\Bridge\Http\Middleware\ValidateLaunchToken;
use Kennofizet\AppHub\Modules\Bridge\Providers\BridgeServiceProvider;
use Kennofizet\AppHub\Modules\Catalog\Providers\CatalogServiceProvider;
use Kennofizet\AppHub\Modules\Launch\Providers\LaunchServiceProvider;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;

class AppHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/apphub.php', 'apphub');

        $this->app->register(CatalogServiceProvider::class);
        $this->app->register(LaunchServiceProvider::class);
        $this->app->register(BridgeServiceProvider::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->publishes([
            __DIR__ . '/Config/apphub.php' => config_path('apphub.php'),
        ], 'apphub-config');

        $this->publishes([
            __DIR__ . '/Modules/Catalog/Database/Migrations' => database_path('migrations'),
            __DIR__ . '/Modules/Launch/Database/Migrations' => database_path('migrations'),
        ], 'apphub-migrations');

        $this->app['router']->aliasMiddleware('apphub.launch.token', ValidateLaunchToken::class);
        $this->app['router']->aliasMiddleware('apphub.host.access', EnsureAppHubHostAccess::class);
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('apphub-bridge', function (Request $request): Limit {
            $token = trim((string) $request->header('X-AppHub-Launch-Token', ''));
            $key = $token !== ''
                ? 'bridge:' . app(LaunchTokenService::class)->hashToken($token)
                : 'bridge-ip:' . (string) $request->ip();

            return Limit::perMinute(max(1, (int) config('apphub.bridge_rate_limit', 30)))->by($key);
        });

        RateLimiter::for('apphub-bridge-user', function (Request $request): Limit {
            $token = trim((string) $request->header('X-AppHub-Launch-Token', ''));
            $key = $token !== ''
                ? 'bridge-user:' . app(LaunchTokenService::class)->hashToken($token)
                : 'bridge-user-ip:' . (string) $request->ip();

            return Limit::perMinute(max(1, (int) config('apphub.bridge_user_rate_limit', 15)))->by($key);
        });

        RateLimiter::for('apphub-bridge-notify', function (Request $request): Limit {
            $token = trim((string) $request->header('X-AppHub-Launch-Token', ''));
            $key = $token !== ''
                ? 'bridge-notify:' . app(LaunchTokenService::class)->hashToken($token)
                : 'bridge-notify-ip:' . (string) $request->ip();

            return Limit::perMinute(max(1, (int) config('apphub.bridge_notify_rate_limit', 10)))->by($key);
        });

        RateLimiter::for('apphub-verify-launch', function (Request $request): Limit {
            $token = trim((string) ($request->input('launch_token') ?? ''));
            $key = $token !== ''
                ? 'verify:' . app(LaunchTokenService::class)->hashToken($token)
                : 'verify-ip:' . (string) $request->ip();

            return Limit::perMinute(max(1, (int) config('apphub.verify_launch_rate_limit', 20)))->by($key);
        });
    }
}
