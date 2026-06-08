<?php declare(strict_types=1);

namespace Kennofizet\AppHub;

use Illuminate\Support\ServiceProvider;
use Kennofizet\AppHub\Http\Middleware\EnsureAppHubHostAccess;
use Kennofizet\AppHub\Http\Middleware\ValidateLaunchToken;
use Kennofizet\AppHub\Support\LaunchTokenService;

class AppHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/apphub.php', 'apphub');
        $this->app->singleton(LaunchTokenService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/Config/apphub.php' => config_path('apphub.php'),
        ], 'apphub-config');

        $this->app['router']->aliasMiddleware('apphub.launch.token', ValidateLaunchToken::class);
        $this->app['router']->aliasMiddleware('apphub.host.access', EnsureAppHubHostAccess::class);
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }
}
