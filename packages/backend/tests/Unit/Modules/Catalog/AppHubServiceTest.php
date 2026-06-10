<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use PHPUnit\Framework\TestCase;

final class AppHubServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        if (function_exists('app') && app()->bound('config')) {
            app('config')->set('apphub.dev_user_ids', []);
        }

        parent::tearDown();
    }

    public function test_is_dev_user_matches_configured_ids(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        app('config')->set('apphub.dev_user_ids', [1, 42]);
        $service = new AppHubService();

        $this->assertTrue($service->isDevUser(1));
        $this->assertTrue($service->isDevUser(42));
        $this->assertFalse($service->isDevUser(2));
        $this->assertFalse($service->isDevUser(null));
    }
}
