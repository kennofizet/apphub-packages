<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Providers;

use Kennofizet\AppHub\Core\Providers\ModuleServiceProvider;

class BridgeServiceProvider extends ModuleServiceProvider
{
    public static function moduleKey(): string
    {
        return 'bridge';
    }
}
