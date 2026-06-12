<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppPublishService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use PHPUnit\Framework\TestCase;

final class AppCatalogReviewFieldsTest extends TestCase
{
    protected function tearDown(): void
    {
        if (function_exists('app') && app()->bound('config')) {
            app('config')->set('apphub.dev_user_ids', []);
        }

        parent::tearDown();
    }

    public function test_store_catalog_hides_review_fields_from_zone_users(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        $catalog = new AppCatalogService($this->appHubService());
        $app = $this->sampleApp(['owner_user_id' => 1]);

        $item = $catalog->toCatalogItem($app, 999, AppCatalogMode::STORE);

        $this->assertNull($item['pending_version']);
        $this->assertNull($item['rejected_version']);
    }

    public function test_store_catalog_shows_review_fields_to_owner(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        $catalog = new AppCatalogService($this->appHubService());
        $app = $this->sampleApp(['owner_user_id' => 42]);

        $item = $catalog->toCatalogItem($app, 42, AppCatalogMode::STORE);

        $this->assertSame('1.1.0', $item['pending_version']);
    }

    public function test_publisher_catalog_shows_review_fields(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        $catalog = new AppCatalogService($this->appHubService());
        $app = $this->sampleApp(['owner_user_id' => 1]);

        $item = $catalog->toCatalogItem($app, 1, AppCatalogMode::PUBLISHER);

        $this->assertSame('1.1.0', $item['pending_version']);
    }

    /** @param array<string, mixed> $overrides */
    private function sampleApp(array $overrides = []): App
    {
        $app = new App();
        $app->forceFill(array_merge([
            'slug' => 'demo-app',
            'name' => 'Demo',
            'version' => '1.0.0',
            'pending_version' => '1.1.0',
            'status' => AppStatus::ACTIVE,
            'runtime_type' => 'hosted',
            'manifest' => ['permissions' => ['user.read']],
        ], $overrides));

        return $app;
    }

    private function appHubService(): AppHubService
    {
        app('config')->set('apphub.dev_user_ids', []);

        return new AppHubService($this->createMock(AppPublishService::class));
    }
}
