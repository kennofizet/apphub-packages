<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use PHPUnit\Framework\TestCase;

final class AppCatalogUserZoneIdsTest extends TestCase
{
    public function test_normalize_user_zone_ids_dedupes_and_filters(): void
    {
        $this->assertSame(
            [1, 2, 5],
            AppCatalogService::normalizeUserZoneIds([1, '2', 2, 0, -1, null, 5]),
        );
    }

    public function test_normalize_user_zone_ids_empty_input(): void
    {
        $this->assertSame([], AppCatalogService::normalizeUserZoneIds([]));
    }
}
