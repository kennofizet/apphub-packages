<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestParser;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AppManifestParserIframeTest extends TestCase
{
    private AppManifestParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AppManifestParser();
    }

    public function test_rejects_missing_entry_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entry_url is required');

        $this->parser->normalizeIframe([
            'slug' => 'my-iframe',
            'name' => 'My Iframe',
            'version' => '1.0.0',
            'runtime_type' => 'iframe',
        ]);
    }

    public function test_rejects_wrong_runtime_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('runtime_type must be "iframe"');

        $this->parser->normalizeIframe([
            'slug' => 'my-iframe',
            'name' => 'My Iframe',
            'version' => '1.0.0',
            'runtime_type' => 'hosted',
            'entry_url' => 'https://tools.reg.local/apps/demo/',
        ]);
    }

    public function test_normalizes_valid_iframe_manifest_when_url_allowed(): void
    {
        if (!function_exists('app') || !app()->bound('config')) {
            $this->markTestSkipped('Laravel app container not available');
        }

        app('config')->set('apphub.allow_localhost_api_urls', true);
        app('config')->set('apphub.allowed_runtime_origins', []);

        $meta = $this->parser->normalizeIframe([
            'slug' => 'my-iframe',
            'name' => 'My Iframe App',
            'version' => '1.0.0',
            'description' => 'Self-hosted',
            'runtime_type' => 'iframe',
            'entry_url' => 'http://localhost:3000/my-app/',
            'permissions' => ['user.read'],
        ]);

        $this->assertSame('my-iframe', $meta['slug']);
        $this->assertSame('1.0.0', $meta['version']);
        $this->assertSame(AppRuntimeType::IFRAME, $meta['runtime_type']);
        $this->assertSame('http://localhost:3000/my-app/', $meta['entry_url']);
        $this->assertSame(['user.read'], $meta['manifest']['permissions']);
    }
}
