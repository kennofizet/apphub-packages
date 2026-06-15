<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Providers;

use Kennofizet\AppHub\Core\Providers\ModuleServiceProvider;
use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckService;
use Kennofizet\AppHub\Modules\Launch\Services\AppLaunchCallerUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\AppUsageService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;

class LaunchServiceProvider extends ModuleServiceProvider
{
    public static function moduleKey(): string
    {
        return 'launch';
    }

    public function register(): void
    {
        $this->app->singleton(LaunchTokenService::class);
        $this->app->singleton(LaunchService::class);
        $this->app->singleton(AppUsageService::class);
        $this->app->singleton(AppHealthcheckService::class);
        $this->app->singleton(AppLaunchCallerUrlGuard::class);
    }

    public function boot(): void
    {
        $this->loadModuleMigrations();
    }
}
