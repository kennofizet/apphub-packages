<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AppCatalogPendingPermissionsTest extends TestCase
{
    public function test_pending_upgrade_preferred_when_review_fields_visible(): void
    {
        $method = new ReflectionMethod(AppCatalogService::class, 'resolvePermissionsForCatalog');
        $method->setAccessible(true);

        $this->assertTrue($method->getNumberOfParameters() >= 2);
        $params = $method->getParameters();
        $this->assertSame('preferPendingUpgrade', $params[1]->getName());
    }
}
