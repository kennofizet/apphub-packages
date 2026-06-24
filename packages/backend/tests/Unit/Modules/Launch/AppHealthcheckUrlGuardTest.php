<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Launch;

use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchDeniedException;
use PHPUnit\Framework\TestCase;

final class AppHealthcheckUrlGuardTest extends TestCase
{
    public function test_rejects_private_ip_literal(): void
    {
        $this->expectException(LaunchDeniedException::class);

        AppHealthcheckUrlGuard::assertSafeUrl('https://10.0.0.5/health');
    }

    public function test_allows_https_public_host(): void
    {
        AppHealthcheckUrlGuard::assertSafeUrl('https://example.com/health');

        $this->addToAssertionCount(1);
    }

    public function test_allows_localhost_in_dev_mode(): void
    {
        AppHealthcheckUrlGuard::assertSafeUrl('http://localhost:8080/health');

        $this->addToAssertionCount(1);
    }
}
