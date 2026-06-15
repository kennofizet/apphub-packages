<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use PHPUnit\Framework\TestCase;

final class AppManifestApiUrlTest extends TestCase
{
    public function test_normalize_list_and_legacy_api_base_url(): void
    {
        $urls = AppManifestApiUrl::fromManifest([
            'api_base_url' => 'https://api.example.com/',
            'api_urls' => ['https://tools.reg.local/apps/demo', 'http://localhost:3000'],
        ]);

        $this->assertSame([
            'https://api.example.com',
            'https://tools.reg.local/apps/demo',
            'http://localhost:3000',
        ], $urls);
    }

    public function test_matches_allowed_origin_and_path_prefix(): void
    {
        $allowed = ['https://tools.reg.local/apps/demo'];

        $this->assertTrue(AppManifestApiUrl::matchesAllowed(
            'https://tools.reg.local/apps/demo/verify',
            $allowed,
        ));
        $this->assertFalse(AppManifestApiUrl::matchesAllowed(
            'https://evil.example.com/apps/demo',
            $allowed,
        ));
    }

    public function test_rejects_urls_with_credentials(): void
    {
        $this->assertNull(AppManifestApiUrl::normalizeSingle('https://user:pass@api.example.com'));
    }

    public function test_assert_required_throws_when_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('api_urls is required');

        AppManifestApiUrl::assertRequired(['slug' => 'demo']);
    }
}
