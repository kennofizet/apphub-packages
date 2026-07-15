<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Bridge;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeDemoFixtures;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeRegistry;
use PHPUnit\Framework\TestCase;

final class ParentBridgeDemoFixturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRegistryConfig([
            'defaults' => [
                'demo_data' => [
                    'project.list' => [
                        ['id' => 1, 'code' => 'X'],
                        ['id' => 2, 'code' => 'Y'],
                    ],
                    'project.members' => [
                        ['userId' => 2, 'name' => 'Fallback Member'],
                    ],
                    'signature.user' => [
                        'url' => null,
                        'mime' => 'image/png',
                        'data' => 'abc',
                    ],
                ],
            ],
            'actions' => [
                'project.list' => [
                    'bridge_scope' => 'parent.project.list',
                ],
                'project.members' => [
                    'bridge_scope' => 'parent.project.members',
                    'demo_data' => [
                        ['userId' => 9, 'name' => 'Override Member'],
                    ],
                ],
                'signature.user' => [
                    'bridge_scope' => 'parent.signature.user',
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetParentBridgeRegistry();
        parent::tearDown();
    }

    public function test_list_fixture_remains_php_list_without_root_demo_marker(): void
    {
        $demo = ParentBridgeDemoFixtures::forAction('project.list');

        $this->assertIsArray($demo);
        $this->assertTrue(array_is_list($demo));
        $this->assertArrayNotHasKey('_demo_fixture', $demo);
        $this->assertCount(2, $demo);
        $this->assertTrue($demo[0]['_demo_fixture']);
        $this->assertTrue($demo[1]['_demo_fixture']);
        $this->assertSame(1, $demo[0]['id']);
        $this->assertSame('Y', $demo[1]['code']);
    }

    public function test_list_fixture_json_encodes_as_array(): void
    {
        $demo = ParentBridgeDemoFixtures::forAction('project.list');
        $json = json_encode($demo, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertStringStartsWith('[', $json);
        $this->assertIsArray($decoded);
        $this->assertTrue(array_is_list($decoded));
        $this->assertArrayNotHasKey('_demo_fixture', $decoded);
        $this->assertTrue($decoded[0]['_demo_fixture']);
    }

    public function test_members_list_fixture_marks_each_row(): void
    {
        $demo = ParentBridgeDemoFixtures::forAction('project.members');

        $this->assertIsArray($demo);
        $this->assertTrue(array_is_list($demo));
        $this->assertArrayNotHasKey('_demo_fixture', $demo);
        $this->assertTrue($demo[0]['_demo_fixture']);
        $this->assertSame(9, $demo[0]['userId']);
        $this->assertSame('Override Member', $demo[0]['name']);
    }

    public function test_associative_fixture_marks_root_object(): void
    {
        $demo = ParentBridgeDemoFixtures::forAction('signature.user');

        $this->assertIsArray($demo);
        $this->assertFalse(array_is_list($demo));
        $this->assertTrue($demo['_demo_fixture']);
        $this->assertSame('image/png', $demo['mime']);
        $this->assertSame('abc', $demo['data']);
    }

    public function test_for_action_uses_defaults_when_action_has_no_demo_data(): void
    {
        $demo = ParentBridgeDemoFixtures::forAction('project.list');

        $this->assertIsArray($demo);
        $this->assertSame('X', $demo[0]['code']);
    }

    public function test_for_action_prefers_action_demo_data_over_defaults(): void
    {
        $demo = ParentBridgeDemoFixtures::forAction('project.members');

        $this->assertIsArray($demo);
        $this->assertSame(9, $demo[0]['userId']);
        $this->assertSame('Override Member', $demo[0]['name']);
    }

    public function test_for_manifest_filters_to_declared_actions(): void
    {
        $manifest = [
            'parent_bridge' => [
                'actions' => [
                    ['name' => 'project.list', 'scope' => 'parent.project.list'],
                    ['name' => 'missing.action', 'scope' => 'parent.project.list'],
                ],
            ],
        ];

        $map = ParentBridgeDemoFixtures::forManifest($manifest);

        $this->assertArrayHasKey('project.list', $map);
        $this->assertArrayNotHasKey('missing.action', $map);
        $this->assertTrue(array_is_list($map['project.list']));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function seedRegistryConfig(array $config): void
    {
        $this->resetParentBridgeRegistry();
        $ref = new \ReflectionClass(ParentBridgeRegistry::class);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $prop->setValue(null, $config);
    }

    private function resetParentBridgeRegistry(): void
    {
        $ref = new \ReflectionClass(ParentBridgeRegistry::class);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
