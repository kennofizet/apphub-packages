<?php declare(strict_types=1);

namespace Kennofizet\AppHub;

use Illuminate\Support\ServiceProvider;
use Kennofizet\AppHub\Modules\Bridge\Http\Middleware\EnsureAppHubHostAccess;
use Kennofizet\AppHub\Modules\Bridge\Http\Middleware\ValidateLaunchToken;
use Kennofizet\AppHub\Modules\Bridge\Providers\BridgeServiceProvider;
use Kennofizet\AppHub\Modules\Catalog\Providers\CatalogServiceProvider;
use Kennofizet\AppHub\Modules\Launch\Providers\LaunchServiceProvider;

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
}
