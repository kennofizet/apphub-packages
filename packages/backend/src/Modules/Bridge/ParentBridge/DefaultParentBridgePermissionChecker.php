<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgePermissionChecker;
use Kennofizet\AppHub\Traits\InteractsWithAppHub;
use Kennofizet\PackagesCore\Models\User;

/**
 * Default: allow when permission is null; deny named permissions unless user uses InteractsWithAppHub.
 * Hosts should bind a custom ParentBridgePermissionChecker that loads App\Models\User for RBAC.
 */
final class DefaultParentBridgePermissionChecker implements ParentBridgePermissionChecker
{
    public function can(User $user, ?string $permission, array $context): bool
    {
        unset($context);

        if ($permission === null || $permission === '') {
            return true;
        }

        if (in_array(InteractsWithAppHub::class, class_uses_recursive($user), true)
            && method_exists($user, 'hubBridgeCan')) {
            return $user->hubBridgeCan($permission);
        }

        return false;
    }
}
