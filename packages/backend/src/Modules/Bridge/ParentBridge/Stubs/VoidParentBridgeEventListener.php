<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge\Stubs;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgeEventListener;
use Kennofizet\PackagesCore\Models\User;

/** Default stub — no-op until host replaces listener in config. */
final class VoidParentBridgeEventListener implements ParentBridgeEventListener
{
    public function handle(User $user, array $payload): void
    {
    }
}
