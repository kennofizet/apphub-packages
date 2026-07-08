<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

use Illuminate\Support\Facades\Log;

final class ParentBridgeAuditLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(string $kind, array $context): void
    {
        if (!ParentBridgeRegistry::auditLogEnabled()) {
            return;
        }

        Log::channel(config('apphub-parent-bridge.security.audit_channel', 'stack'))
            ->info('apphub.parent_bridge.' . $kind, $context);
    }
}
