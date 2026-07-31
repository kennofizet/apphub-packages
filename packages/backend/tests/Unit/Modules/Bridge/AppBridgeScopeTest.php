<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Bridge;

use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use PHPUnit\Framework\TestCase;

final class AppBridgeScopeTest extends TestCase
{
    public function test_normalize_list_accepts_strings_and_objects(): void
    {
        $scopes = AppBridgeScope::normalizeList([
            'user.read',
            ['scope' => 'desktop.notify'],
            'invalid.scope',
            'user.read',
        ]);

        $this->assertSame(['user.read', 'desktop.notify'], $scopes);
    }

    public function test_from_manifest_reads_permissions(): void
    {
        $scopes = AppBridgeScope::fromManifest([
            'permissions' => ['user.profile', 'desktop.badge'],
        ]);

        $this->assertSame(['user.profile', 'desktop.badge'], $scopes);
    }

    public function test_desktop_theme_is_a_valid_bridge_scope(): void
    {
        $this->assertTrue(AppBridgeScope::isValid('desktop.theme'));
        $this->assertContains('desktop.theme', AppBridgeScope::ALL);
        $this->assertSame(
            ['desktop.theme'],
            AppBridgeScope::normalizeList(['desktop.theme', 'desktop.theme']),
        );
    }
}
