<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Providers;

use Kennofizet\AppHub\Core\Providers\ModuleServiceProvider;
use Kennofizet\AppHub\Modules\Catalog\Services\AppBundleStorageService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppPublishService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppRuntimeServeService;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public static function moduleKey(): string
    {
        return 'catalog';
    }

    public function register(): void
    {
        $this->app->singleton(AppHubService::class);
        $this->app->singleton(AppCatalogService::class);
        $this->app->singleton(AppBundleStorageService::class);
        $this->app->singleton(AppPublishService::class);
        $this->app->singleton(AppRuntimeServeService::class);
    }

    public function boot(): void
    {
        $this->loadModuleMigrations();
    }
}
