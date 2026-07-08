<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge\Stubs;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgeAction;
use Kennofizet\PackagesCore\Models\User;

/** Default stub — returns null until host replaces handler in config. */
final class NullArrayParentBridgeAction implements ParentBridgeAction
{
    public function handle(User $user, array $args): mixed
    {
        return null;
    }
}
