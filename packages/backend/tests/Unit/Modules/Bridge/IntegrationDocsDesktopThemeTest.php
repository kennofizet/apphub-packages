<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Bridge;

use Kennofizet\AppHub\Modules\Bridge\Support\IntegrationDocs;
use PHPUnit\Framework\TestCase;

final class IntegrationDocsDesktopThemeTest extends TestCase
{
    public function test_publisher_contract_exposes_desktop_theme_bridge_api(): void
    {
        $docs = IntegrationDocs::read();

        $this->assertSame('1.20.0', $docs['schema_version'] ?? null);

        $publisher = $docs['audiences']['publisher'] ?? [];
        $api = $publisher['bridge']['javascript_api']['applyDesktopTheme'] ?? null;
        $this->assertIsArray($api);
        $this->assertSame(
            ['desktop.theme'],
            $api['requires'] ?? null,
        );
        $this->assertStringContainsString('skin?', (string) ($api['signature'] ?? ''));
        $this->assertContains('cyber', $api['allowed_skins'] ?? []);
        $this->assertContains('--ah-accent', $api['allowed_tokens'] ?? []);
        $this->assertContains('--ah-fx-panel-rise', $api['allowed_tokens'] ?? []);
        $this->assertContains('.apphub-win', $api['allowed_rule_selectors'] ?? []);
        $this->assertContains('.apphub-start__app-tile', $api['allowed_rule_selectors'] ?? []);
        $this->assertContains('.apphub-icon-menu__item', $api['allowed_rule_selectors'] ?? []);
        $this->assertStringContainsString('skin:', (string) ($api['example'] ?? ''));

        $permissions = $publisher['bridge']['permissions'] ?? [];
        $scopes = array_column($permissions, 'scope');
        $this->assertContains('desktop.theme', $scopes);
    }
}
