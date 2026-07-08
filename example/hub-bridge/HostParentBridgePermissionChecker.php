<?php declare(strict_types=1);

namespace App\HubBridge;

use App\Models\User;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgePermissionChecker;
use Kennofizet\PackagesCore\Models\User as CoreUser;

/**
 * Host RBAC for parent bridge — copy into your Laravel app and set:
 * APPHUB_PARENT_BRIDGE_PERMISSION_CHECKER=App\HubBridge\HostParentBridgePermissionChecker
 */
final class HostParentBridgePermissionChecker implements ParentBridgePermissionChecker
{
    public function can(CoreUser $user, ?string $permission, array $context): bool
    {
        unset($context);

        if ($permission === null || $permission === '') {
            return false;
        }

        $hostUser = User::query()->find($user->id);
        if ($hostUser === null) {
            return false;
        }

        if (!method_exists($hostUser, 'hubBridgeCan')) {
            return false;
        }

        return $hostUser->hubBridgeCan($permission);
    }
}
