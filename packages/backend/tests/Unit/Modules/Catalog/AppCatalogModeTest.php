<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;
use PHPUnit\Framework\TestCase;

final class AppCatalogModeTest extends TestCase
{
    public function test_normalize_defaults_to_store(): void
    {
        $this->assertSame(AppCatalogMode::STORE, AppCatalogMode::normalize(null));
        $this->assertSame(AppCatalogMode::STORE, AppCatalogMode::normalize(''));
        $this->assertSame(AppCatalogMode::STORE, AppCatalogMode::normalize('invalid'));
    }

    public function test_normalize_accepts_draft(): void
    {
        $this->assertSame(AppCatalogMode::DRAFT, AppCatalogMode::normalize('draft'));
        $this->assertSame(AppCatalogMode::DRAFT, AppCatalogMode::normalize('DRAFT'));
        $this->assertSame(AppCatalogMode::PUBLISHER, AppCatalogMode::normalize('publisher'));
    }
}
