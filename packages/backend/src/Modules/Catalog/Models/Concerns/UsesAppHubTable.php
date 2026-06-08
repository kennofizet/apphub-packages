<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Models\Concerns;

trait UsesAppHubTable
{
    protected static function apphubTable(string $configKey, string $default): string
    {
        return (string) config("apphub.{$configKey}", $default);
    }
}
