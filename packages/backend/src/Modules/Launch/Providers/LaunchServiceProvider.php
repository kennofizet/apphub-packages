<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Providers;

use Kennofizet\AppHub\Core\Providers\ModuleServiceProvider;
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
    }

    public function boot(): void
    {
        $this->loadModuleMigrations();
    }
}
