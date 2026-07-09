<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

/** Sanitized host config catalog for GET parent-bridge/catalog (dev docs). */
final class ParentBridgeCatalog
{
    /**
     * @return array{
     *     enabled: bool,
     *     scopes: array<string, array{description?: string, user_prompt?: string}>,
     *     actions: list<array<string, mixed>>,
     *     events: list<array<string, mixed>>
     * }
     */
    public static function forHost(): array
    {
        $config = ParentBridgeRegistry::config();

        return [
            'enabled' => ParentBridgeRegistry::isEnabled(),
            'scopes' => self::scopes($config),
            'actions' => self::actions($config),
            'events' => self::events($config),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, array{description?: string, user_prompt?: string}>
     */
    private static function scopes(array $config): array
    {
        $raw = $config['scopes'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $scope => $meta) {
            if (!is_string($scope) || $scope === '' || !is_array($meta)) {
                continue;
            }
            $row = [];
            if (isset($meta['description']) && is_string($meta['description']) && $meta['description'] !== '') {
                $row['description'] = $meta['description'];
            }
            if (isset($meta['user_prompt']) && is_string($meta['user_prompt']) && $meta['user_prompt'] !== '') {
                $row['user_prompt'] = $meta['user_prompt'];
            }
            $out[$scope] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private static function actions(array $config): array
    {
        $raw = $config['actions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $name => $entry) {
            if (!is_string($name) || $name === '' || !is_array($entry)) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'bridge_scope' => $entry['bridge_scope'] ?? null,
                'permission' => $entry['permission'] ?? null,
                'mode' => $entry['mode'] ?? null,
                'enabled' => !isset($entry['enabled']) || filter_var($entry['enabled'], FILTER_VALIDATE_BOOL),
                'args' => is_array($entry['args'] ?? null) ? array_keys($entry['args']) : [],
                'returns' => $entry['returns'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private static function events(array $config): array
    {
        $raw = $config['events'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $name => $entry) {
            if (!is_string($name) || $name === '' || !is_array($entry)) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'bridge_scope' => $entry['bridge_scope'] ?? null,
                'permission' => $entry['permission'] ?? null,
                'enabled' => !isset($entry['enabled']) || filter_var($entry['enabled'], FILTER_VALIDATE_BOOL),
                'payload' => is_array($entry['payload'] ?? null) ? array_keys($entry['payload']) : [],
            ];
        }

        return $out;
    }
}
