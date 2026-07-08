<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts;

use Kennofizet\PackagesCore\Models\User;

interface ParentBridgeAction
{
    /**
     * @param array<string, mixed> $args Validated args from config schema
     */
    public function handle(User $user, array $args): mixed;
}
