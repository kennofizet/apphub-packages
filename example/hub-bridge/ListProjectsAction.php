<?php declare(strict_types=1);

namespace App\HubBridge;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgeAction;
use Kennofizet\PackagesCore\Models\User;

/**
 * Sample parent-bridge handler — returns null until you wire your production query.
 *
 * Copy to App\HubBridge\ and reference from config/apphub-parent-bridge.php.
 */
final class ListProjectsAction implements ParentBridgeAction
{
    public function handle(User $user, array $args): mixed
    {
        unset($user, $args);

        return null;
    }
}
