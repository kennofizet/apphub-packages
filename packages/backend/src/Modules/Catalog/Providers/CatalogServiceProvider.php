<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Providers;

use Kennofizet\AppHub\Core\Providers\ModuleServiceProvider;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;

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
    }

    public function boot(): void
    {
        $this->loadModuleMigrations();
    }
}
