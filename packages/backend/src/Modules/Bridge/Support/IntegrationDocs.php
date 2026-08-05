<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Support;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeRegistry;

final class IntegrationDocs
{
    /** @var list<string> */
    private const PUBLISHER_SAFE_ENRICHMENT_KEYS = [
        'summary',
        'description',
        'profiles',
        'nested',
        'requirements',
        'notes',
        'examples',
        'example',
        'reader_note',
    ];

    /** @var list<string> */
    private const BLOCKED_ENRICHMENT_KEYS = [
        'handler',
        'listener',
        'permission',
        'permission_checker',
        'security',
        'allowed_handler_namespaces',
        'demo_data',
        'fqcn',
        'class',
        'namespace',
    ];

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
     * Merge publisher-safe parent-bridge action contracts from host config.
     * Never exposes handler FQCNs, permission checker internals, or security allowlists.
     */
    public static function withRuntimeParentActions(array $doc): array
    {
        $actions = self::parentActionContractsFromConfig();
        if ($actions === []) {
            return $doc;
        }

        self::applyParentActionContracts($doc, $actions);

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
     * @return array<string, array<string, mixed>>
     */
    public static function parentActionContractsFromConfig(): array
    {
        $config = ParentBridgeRegistry::config();
        $raw = $config['actions'] ?? [];
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        $enrichment = self::loadPublisherActionEnrichment($config);
        $out = [];

        foreach ($raw as $name => $entry) {
            if (!is_string($name) || trim($name) === '' || !is_array($entry)) {
                continue;
            }

            $actionName = strtolower(trim($name));
            $scope = isset($entry['bridge_scope']) && is_string($entry['bridge_scope'])
                ? trim($entry['bridge_scope'])
                : '';
            if ($scope === '') {
                continue;
            }

            $contract = [
                'name' => $actionName,
                'scope' => $scope,
            ];

            $mode = isset($entry['mode']) && is_string($entry['mode']) ? trim($entry['mode']) : '';
            if ($mode !== '') {
                $contract['mode'] = $mode;
            }

            if (array_key_exists('schema_version', $entry)) {
                $schemaVersion = $entry['schema_version'];
                if (is_int($schemaVersion) || is_float($schemaVersion) || is_string($schemaVersion)) {
                    $contract['schema_version'] = $schemaVersion;
                }
            }

            if (isset($entry['args']) && is_array($entry['args'])) {
                $contract['args'] = $entry['args'];
            }

            if (isset($entry['returns']) && is_array($entry['returns'])) {
                $contract['returns'] = $entry['returns'];
            }

            $extra = $enrichment[$actionName] ?? null;
            if (is_array($extra) && $extra !== []) {
                foreach ($extra as $key => $value) {
                    if (!is_string($key) || $key === '' || array_key_exists($key, $contract)) {
                        continue;
                    }
                    $contract[$key] = $value;
                }
            }

            $out[$actionName] = $contract;
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

    /**
     * @param array<string, array<string, mixed>> $actions
     */
    private static function applyParentActionContracts(array &$doc, array $actions): void
    {
        if (!isset($doc['audiences']['publisher']['bridge']) || !is_array($doc['audiences']['publisher']['bridge'])) {
            $doc['audiences']['publisher']['bridge'] = [];
        }

        $bridge = &$doc['audiences']['publisher']['bridge'];
        if (!isset($bridge['parent_bridge']) || !is_array($bridge['parent_bridge'])) {
            $bridge['parent_bridge'] = [];
        }

        $bridge['parent_bridge']['host_action_contracts'] = [
            'live_from_host_config' => true,
            'reader_note' => 'Host-provided callParent contracts (args/returns). Live from this product.',
            'actions' => $actions,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, array<string, mixed>>
     */
    private static function loadPublisherActionEnrichment(array $config): array
    {
        $path = self::resolvePublisherActionsPath($config);
        if ($path === null) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $rawActions = $decoded['actions'] ?? $decoded;
        if (!is_array($rawActions)) {
            return [];
        }

        $out = [];
        foreach ($rawActions as $name => $meta) {
            if (!is_string($name) || trim($name) === '' || !is_array($meta)) {
                continue;
            }
            $safe = self::sanitizePublisherEnrichment($meta);
            if ($safe !== []) {
                $out[strtolower(trim($name))] = $safe;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function resolvePublisherActionsPath(array $config): ?string
    {
        $integrationDocs = $config['integration_docs'] ?? [];
        $configured = is_array($integrationDocs)
            ? trim((string) ($integrationDocs['publisher_actions_path'] ?? ''))
            : '';

        if ($configured !== '') {
            $resolved = self::resolveReadablePath($configured);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $convention = self::safeBasePath('integration-docs/publisher/parent_bridge.actions.json');
        if ($convention !== null && is_readable($convention)) {
            return $convention;
        }

        return null;
    }

    private static function resolveReadablePath(string $path): ?string
    {
        if (self::isAbsolutePath($path) && is_readable($path)) {
            return $path;
        }

        $resolved = self::safeBasePath(ltrim(str_replace('\\', '/', $path), '/'));
        if ($resolved !== null && is_readable($resolved)) {
            return $resolved;
        }

        return null;
    }

    private static function safeBasePath(string $relative = ''): ?string
    {
        if (!function_exists('base_path')) {
            return null;
        }

        try {
            return $relative === '' ? base_path() : base_path($relative);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    /**
     * Top-level enrichment keys must be publisher-safe; nested structures are
     * recursively redacted so blocklisted keys cannot be smuggled under
     * containers like nested / examples / profiles.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function sanitizePublisherEnrichment(array $meta): array
    {
        $out = [];
        foreach ($meta as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $normalized = strtolower($key);
            if (in_array($normalized, self::BLOCKED_ENRICHMENT_KEYS, true)) {
                continue;
            }
            if (!in_array($normalized, self::PUBLISHER_SAFE_ENRICHMENT_KEYS, true)) {
                continue;
            }
            $out[$key] = self::redactBlockedEnrichmentValue($value);
        }

        return $out;
    }

    private static function redactBlockedEnrichmentValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        $isList = array_is_list($value);
        foreach ($value as $key => $child) {
            if (is_string($key) && in_array(strtolower($key), self::BLOCKED_ENRICHMENT_KEYS, true)) {
                continue;
            }
            if ($isList) {
                $out[] = self::redactBlockedEnrichmentValue($child);
            } else {
                $out[$key] = self::redactBlockedEnrichmentValue($child);
            }
        }

        return $out;
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
