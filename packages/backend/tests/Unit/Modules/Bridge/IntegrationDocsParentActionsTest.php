<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Bridge;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeRegistry;
use Kennofizet\AppHub\Modules\Bridge\Support\IntegrationDocs;
use PHPUnit\Framework\TestCase;

final class IntegrationDocsParentActionsTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetParentBridgeRegistry();
        parent::tearDown();
    }

    public function test_empty_actions_leave_docs_unchanged(): void
    {
        $this->seedRegistryConfig(['actions' => []]);

        $doc = ['audiences' => ['publisher' => ['bridge' => ['parent_bridge' => []]]]];
        $merged = IntegrationDocs::withRuntimeParentActions($doc);

        $this->assertArrayNotHasKey(
            'host_action_contracts',
            $merged['audiences']['publisher']['bridge']['parent_bridge'],
        );
    }

    public function test_config_actions_merge_into_publisher_host_action_contracts(): void
    {
        $this->seedRegistryConfig([
            'actions' => [
                'inspection.save' => [
                    'handler' => 'App\\HubBridge\\SecretInspectionSaveAction',
                    'permission' => 'inspections.write',
                    'bridge_scope' => 'parent.inspection.write',
                    'mode' => 'write',
                    'schema_version' => 2,
                    'args' => [
                        'acceptance' => ['type' => 'object', 'required' => true],
                        'calculator' => ['type' => 'object', 'required' => true],
                    ],
                    'returns' => [
                        'type' => 'object',
                        'fields' => ['id', 'status'],
                    ],
                    'demo_data' => ['id' => 1],
                ],
                'project.list' => [
                    'handler' => 'App\\HubBridge\\ProjectListAction',
                    'bridge_scope' => 'parent.project.list',
                    'mode' => 'read',
                    'args' => ['query' => ['optional' => true]],
                    'returns' => ['type' => 'array'],
                ],
            ],
        ]);

        $doc = IntegrationDocs::read();
        $doc = IntegrationDocs::withRuntimeParentScopes($doc);
        $doc = IntegrationDocs::withRuntimeParentActions($doc);
        $publisher = IntegrationDocs::forPublisher($doc);

        $contracts = $publisher['audiences']['publisher']['bridge']['parent_bridge']['host_action_contracts'] ?? null;
        $this->assertIsArray($contracts);
        $this->assertTrue($contracts['live_from_host_config'] ?? false);

        $actions = $contracts['actions'] ?? [];
        $this->assertArrayHasKey('inspection.save', $actions);
        $this->assertSame('inspection.save', $actions['inspection.save']['name'] ?? null);
        $this->assertSame('parent.inspection.write', $actions['inspection.save']['scope'] ?? null);
        $this->assertSame('write', $actions['inspection.save']['mode'] ?? null);
        $this->assertSame(2, $actions['inspection.save']['schema_version'] ?? null);
        $this->assertSame(
            ['acceptance' => ['type' => 'object', 'required' => true], 'calculator' => ['type' => 'object', 'required' => true]],
            $actions['inspection.save']['args'] ?? null,
        );
        $this->assertSame(
            ['type' => 'object', 'fields' => ['id', 'status']],
            $actions['inspection.save']['returns'] ?? null,
        );

        $encoded = json_encode($publisher, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('SecretInspectionSaveAction', $encoded);
        $this->assertStringNotContainsString('inspections.write', $encoded);
        $this->assertStringNotContainsString('demo_data', $encoded);
        $this->assertArrayNotHasKey('handler', $actions['inspection.save']);
        $this->assertArrayNotHasKey('permission', $actions['inspection.save']);
        $this->assertArrayHasKey('project.list', $actions);
    }

    public function test_enrichment_json_merges_publisher_safe_notes_only(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'apphub-actions-');
        $this->assertNotFalse($path);
        file_put_contents($path, json_encode([
            'actions' => [
                'inspection.save' => [
                    'summary' => 'Save KPI inspection batch',
                    'profiles' => ['kpi_multi_unit'],
                    'handler' => 'App\\Should\\Not\\Leak',
                    'security' => ['secret' => true],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->seedRegistryConfig([
                'integration_docs' => [
                    'publisher_actions_path' => $path,
                ],
                'actions' => [
                    'inspection.save' => [
                        'handler' => 'App\\HubBridge\\InspectionSaveAction',
                        'bridge_scope' => 'parent.inspection.write',
                        'mode' => 'write',
                        'args' => ['acceptance' => ['type' => 'object']],
                    ],
                ],
            ]);

            $doc = IntegrationDocs::withRuntimeParentActions(['audiences' => ['publisher' => ['bridge' => []]]]);
            $action = $doc['audiences']['publisher']['bridge']['parent_bridge']['host_action_contracts']['actions']['inspection.save'];

            $this->assertSame('Save KPI inspection batch', $action['summary'] ?? null);
            $this->assertSame(['kpi_multi_unit'], $action['profiles'] ?? null);
            $this->assertArrayNotHasKey('handler', $action);
            $this->assertArrayNotHasKey('security', $action);
        } finally {
            @unlink($path);
        }
    }

    public function test_enrichment_redacts_nested_blocklisted_keys(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'apphub-actions-');
        $this->assertNotFalse($path);
        file_put_contents($path, json_encode([
            'actions' => [
                'inspection.save' => [
                    'summary' => 'Save inspection',
                    'nested' => [
                        'units' => ['label', 'date'],
                        'handler' => 'App\\HubBridge\\SecretInspectionSaveAction',
                        'permission' => 'inspections.write',
                        'security' => ['secret' => true],
                        'deeper' => [
                            'demo_data' => ['id' => 1],
                            'note' => 'safe',
                        ],
                    ],
                    'examples' => [
                        [
                            'label' => 'ok',
                            'handler' => 'App\\Should\\Not\\Leak',
                        ],
                    ],
                    'profiles' => [
                        'kpi' => [
                            'permission_checker' => 'App\\SecretChecker',
                            'title' => 'KPI',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->seedRegistryConfig([
                'integration_docs' => [
                    'publisher_actions_path' => $path,
                ],
                'actions' => [
                    'inspection.save' => [
                        'handler' => 'App\\HubBridge\\InspectionSaveAction',
                        'bridge_scope' => 'parent.inspection.write',
                        'mode' => 'write',
                        'args' => ['acceptance' => ['type' => 'object']],
                    ],
                ],
            ]);

            $doc = IntegrationDocs::withRuntimeParentActions(['audiences' => ['publisher' => ['bridge' => []]]]);
            $action = $doc['audiences']['publisher']['bridge']['parent_bridge']['host_action_contracts']['actions']['inspection.save'];
            $encoded = json_encode($action, JSON_THROW_ON_ERROR);

            $this->assertSame(['label', 'date'], $action['nested']['units'] ?? null);
            $this->assertSame('safe', $action['nested']['deeper']['note'] ?? null);
            $this->assertSame('KPI', $action['profiles']['kpi']['title'] ?? null);
            $this->assertSame('ok', $action['examples'][0]['label'] ?? null);

            $this->assertArrayNotHasKey('handler', $action['nested']);
            $this->assertArrayNotHasKey('permission', $action['nested']);
            $this->assertArrayNotHasKey('security', $action['nested']);
            $this->assertArrayNotHasKey('demo_data', $action['nested']['deeper'] ?? []);
            $this->assertArrayNotHasKey('handler', $action['examples'][0] ?? []);
            $this->assertArrayNotHasKey('permission_checker', $action['profiles']['kpi'] ?? []);

            $this->assertStringNotContainsString('SecretInspectionSaveAction', $encoded);
            $this->assertStringNotContainsString('inspections.write', $encoded);
            $this->assertStringNotContainsString('SecretChecker', $encoded);
        } finally {
            @unlink($path);
        }
    }

    public function test_action_without_bridge_scope_is_skipped(): void
    {
        $this->seedRegistryConfig([
            'actions' => [
                'broken.action' => [
                    'handler' => 'App\\HubBridge\\Broken',
                    'args' => [],
                ],
            ],
        ]);

        $doc = IntegrationDocs::withRuntimeParentActions(['audiences' => ['publisher' => ['bridge' => []]]]);
        $this->assertArrayNotHasKey(
            'host_action_contracts',
            $doc['audiences']['publisher']['bridge']['parent_bridge'] ?? [],
        );
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
