<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Bridge;

use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentService;
use PHPUnit\Framework\TestCase;

final class AppBridgeConsentApproveTest extends TestCase
{
    public function test_dev_approve_hooks_expose_publisher_consent_sync(): void
    {
        $this->assertTrue(method_exists(AppBridgeConsentService::class, 'syncPublisherConsentsForApprovedVersion'));
        $this->assertTrue(method_exists(AppBridgeConsentService::class, 'approveParentBridgeForVersion'));
    }
}
