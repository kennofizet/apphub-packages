<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Base trait for host production parent-bridge permission checks. */
trait InteractsWithAppHub
{
    /**
     * Host must override for production RBAC. Default denies named permissions.
     */
    public function hubBridgeCan(?string $permission): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        return false;
    }

    /**
     * @return array{id: int|string|null, zone_ids?: list<int>}
     */
    public function hubBridgeUserContext(): array
    {
        return [
            'id' => $this->getAuthIdentifier(),
        ];
    }
}
