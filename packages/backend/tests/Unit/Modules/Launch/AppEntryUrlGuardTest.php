<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Launch;

use Kennofizet\AppHub\Modules\Launch\Services\AppEntryUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchDeniedException;
use PHPUnit\Framework\TestCase;

final class AppEntryUrlGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        if (function_exists('app') && app()->bound('config')) {
            app('config')->set('apphub.allow_localhost_api_urls', null);
            app('config')->set('apphub.allowed_runtime_origins', []);
            app('config')->set('apphub.allow_any_publisher_runtime_origin', null);
            app('config')->set('app.env', 'testing');
        }

        parent::tearDown();
    }

    public function test_allows_http_localhost_in_local_env(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        app('config')->set('apphub.allow_localhost_api_urls', true);
        app('config')->set('app.env', 'local');
        app('config')->set('apphub.allowed_runtime_origins', []);

        AppEntryUrlGuard::assertRegisterableUrl('http://localhost:15180/');

        $this->addToAssertionCount(1);
    }

    public function test_allows_https_publisher_origin_when_catalog_trust_opt_in(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        app('config')->set('apphub.allow_localhost_api_urls', false);
        app('config')->set('app.env', 'production');
        app('config')->set('apphub.allowed_runtime_origins', []);
        app('config')->set('apphub.allow_any_publisher_runtime_origin', true);

        AppEntryUrlGuard::assertRegisterableUrl('https://translate.google.com/');

        $this->addToAssertionCount(1);
    }

    public function test_rejects_https_publisher_origin_in_production_without_policy(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        app('config')->set('apphub.allow_localhost_api_urls', false);
        app('config')->set('app.env', 'production');
        app('config')->set('apphub.allowed_runtime_origins', []);
        app('config')->set('apphub.allow_any_publisher_runtime_origin', false);

        $this->expectException(LaunchDeniedException::class);
        $this->expectExceptionMessage('not allowed');

        AppEntryUrlGuard::assertRegisterableUrl('https://translate.google.com/');
    }

    public function test_enterprise_list_blocks_unlisted_origin(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        app('config')->set('apphub.allow_localhost_api_urls', false);
        app('config')->set('app.env', 'production');
        app('config')->set('apphub.allowed_runtime_origins', [
            'https://apps.example.com',
        ]);

        $this->expectException(LaunchDeniedException::class);
        $this->expectExceptionMessage('enterprise allowlist');

        AppEntryUrlGuard::assertRegisterableUrl('https://translate.google.com/');
    }

    public function test_rejects_http_non_localhost_in_production(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        app('config')->set('apphub.allow_localhost_api_urls', false);
        app('config')->set('app.env', 'production');
        app('config')->set('apphub.allowed_runtime_origins', []);

        $this->expectException(LaunchDeniedException::class);
        $this->expectExceptionMessage('App entry URL must use HTTPS');

        AppEntryUrlGuard::assertRegisterableUrl('http://tools.example.com/app/');
    }
}
