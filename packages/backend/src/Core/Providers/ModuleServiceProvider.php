<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Core\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Base for feature modules under src/Modules/{Name}/.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract public static function moduleKey(): string;

    protected function moduleRoot(): string
    {
        $reflection = new \ReflectionClass(static::class);

        return dirname($reflection->getFileName(), 2);
    }

    protected function loadModuleMigrations(): void
    {
        $path = $this->moduleRoot() . '/Database/Migrations';
        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }
}
