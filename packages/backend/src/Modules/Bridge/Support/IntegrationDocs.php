<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Support;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeRegistry;

final class IntegrationDocs
{
    public static function read(): array
    {
        $path = dirname(__DIR__) . '/Resources/integration-docs.json';
        if (!is_readable($path)) {
            throw new \RuntimeException('unavailable');
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Merge live parent.* scope metadata from host config before serving integration-docs.
     */
    public static function withRuntimeParentScopes(array $doc): array
    {
        $entries = self::parentScopeEntriesFromConfig();
        if ($entries === []) {
            return $doc;
        }

        self::applyParentScopeEntries($doc, $entries);

        return $doc;
    }

    /**
     * @return list<array{scope: string, description: string, user_prompt: string, typical_action?: string}>
     */
    public static function parentScopeEntriesFromConfig(): array
    {
        $config = ParentBridgeRegistry::config();
        $raw = $config['scopes'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $scope => $meta) {
            if (!is_string($scope) || $scope === '' || !is_array($meta)) {
                continue;
            }

            $description = isset($meta['description']) && is_string($meta['description'])
                ? trim($meta['description'])
                : '';
            $userPrompt = isset($meta['user_prompt']) && is_string($meta['user_prompt'])
                ? trim($meta['user_prompt'])
                : '';

            $row = [
                'scope' => $scope,
                'description' => $description,
                'user_prompt' => $userPrompt,
            ];

            $typical = self::typicalActionForScope($config, $scope);
            if ($typical !== null && $typical !== '') {
                $row['typical_action'] = $typical;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function typicalActionForScope(array $config, string $scope): ?string
    {
        $actions = $config['actions'] ?? [];
        if (is_array($actions)) {
            foreach ($actions as $name => $entry) {
                if (!is_string($name) || !is_array($entry)) {
                    continue;
                }
                if ((string) ($entry['bridge_scope'] ?? '') === $scope) {
                    return $name;
                }
            }
        }

        $events = $config['events'] ?? [];
        if (is_array($events)) {
            foreach ($events as $name => $entry) {
                if (!is_string($name) || !is_array($entry)) {
                    continue;
                }
                if ((string) ($entry['bridge_scope'] ?? '') === $scope) {
                    return 'emitToParent(' . $name . ')';
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{scope: string, description: string, user_prompt: string, typical_action?: string}> $entries
     */
    private static function applyParentScopeEntries(array &$doc, array $entries): void
    {
        if (!isset($doc['audiences']['publisher']['bridge']) || !is_array($doc['audiences']['publisher']['bridge'])) {
            $doc['audiences']['publisher']['bridge'] = [];
        }

        $bridge = &$doc['audiences']['publisher']['bridge'];
        if (!isset($bridge['parent_scopes']) || !is_array($bridge['parent_scopes'])) {
            $bridge['parent_scopes'] = [];
        }

        $bridge['parent_scopes']['scopes'] = $entries;
        $bridge['parent_scopes']['live_from_host_config'] = true;
    }

    /** Publisher-safe subset — no host_dev, agent, or backend security. */
    public static function forPublisher(array $doc): array
    {
        $routes = array_values(array_filter(
            $doc['http']['routes'] ?? [],
            static fn (array $route): bool => in_array('publisher', $route['audience'] ?? [], true),
        ));

        return [
            'schema_version' => $doc['schema_version'] ?? '1.0.0',
            'package' => $doc['package'] ?? [],
            'audiences' => [
                'publisher' => $doc['audiences']['publisher'] ?? [],
            ],
            'http' => [
                'base_path' => $doc['http']['base_path'] ?? '',
                'routes' => $routes,
            ],
        ];
    }
}
