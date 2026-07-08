<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts;

use Kennofizet\PackagesCore\Models\User;

interface ParentBridgeEventListener
{
    /**
     * @param array<string, mixed> $payload Validated payload from config schema
     */
    public function handle(User $user, array $payload): void;
}
