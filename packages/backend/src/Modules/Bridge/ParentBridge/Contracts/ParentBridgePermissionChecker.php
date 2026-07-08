<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts;

use Kennofizet\PackagesCore\Models\User;

interface ParentBridgePermissionChecker
{
    /**
     * @param array{
     *     app_slug: string,
     *     bridge_scope: string,
     *     action?: string,
     *     event?: string,
     *     session_id?: string|null
     * } $context
     */
    public function can(User $user, ?string $permission, array $context): bool;
}
