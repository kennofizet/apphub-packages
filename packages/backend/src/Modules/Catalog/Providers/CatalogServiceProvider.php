<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Providers;

use Kennofizet\AppHub\Core\Providers\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public static function moduleKey(): string
    {
        return 'catalog';
    }

    public function boot(): void
    {
        $this->loadModuleMigrations();
    }
}
