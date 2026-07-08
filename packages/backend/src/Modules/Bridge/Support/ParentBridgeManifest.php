<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Support;

use RuntimeException;

/** Parse publisher manifest parent_bridge block for install consent + bridge ready catalog. */
final class ParentBridgeManifest
{
    private const ACTION_NAME_PATTERN = '/^[a-z][a-z0-9._-]{0,63}$/';

    private const EVENT_NAME_PATTERN = '/^[a-z][a-z0-9._:-]{0,63}$/';

    /**
     * @param array<string, mixed>|null $manifest
     * @return array{
     *     available: bool,
     *     actions: list<array{name: string, scope: string, description?: string}>,
     *     events: list<array{name: string, scope: string}>
     * }
     */
    public static function catalogFromManifest(?array $manifest): array
    {
        $normalized = self::normalizeBlock($manifest);

        return [
            'available' => $normalized['actions'] !== [] || $normalized['events'] !== [],
            'actions' => array_map(
                static fn (array $row): array => [
                    'name' => $row['name'],
                    'scope' => $row['scope'],
                    ...($row['description'] !== '' ? ['description' => $row['description']] : []),
                ],
                $normalized['actions'],
            ),
            'events' => array_map(
                static fn (array $row): array => [
                    'name' => $row['name'],
                    'scope' => $row['scope'],
                ],
                $normalized['events'],
            ),
        ];
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @return array{actions: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    public static function normalizeBlock(?array $manifest): array
    {
        if ($manifest === null) {
            return ['actions' => [], 'events' => []];
        }

        $raw = $manifest['parent_bridge'] ?? null;
        if (!is_array($raw)) {
            return ['actions' => [], 'events' => []];
        }

        $actions = self::normalizeActions($raw['actions'] ?? null);
        $events = self::normalizeEvents($raw['events'] ?? null);

        return [
            'actions' => $actions,
            'events' => $events,
        ];
    }

    /**
     * Merge parent_bridge scopes into manifest permissions list.
     *
     * @param list<string> $permissions
     * @param array<string, mixed>|null $manifest
     * @return list<string>
     */
    public static function mergePermissionScopes(array $permissions, ?array $manifest): array
    {
        $block = self::normalizeBlock($manifest);
        $scopes = $permissions;

        foreach ([...$block['actions'], ...$block['events']] as $row) {
            $scope = $row['scope'];
            if (!in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /**
     * @param array{actions: list<array<string, mixed>>, events: list<array<string, mixed>>} $block
     * @return array{name: string, scope: string}|null
     */
    public static function findActionInBlock(array $block, string $name): ?array
    {
        $key = strtolower(trim($name));
        foreach ($block['actions'] as $row) {
            if (($row['name'] ?? '') === $key) {
                return [
                    'name' => (string) $row['name'],
                    'scope' => (string) $row['scope'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array{actions: list<array<string, mixed>>, events: list<array<string, mixed>>} $block
     * @return array{name: string, scope: string}|null
     */
    public static function findEventInBlock(array $block, string $name): ?array
    {
        $key = strtolower(trim($name));
        foreach ($block['events'] as $row) {
            if (($row['name'] ?? '') === $key) {
                return [
                    'name' => (string) $row['name'],
                    'scope' => (string) $row['scope'],
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, scope: string, description: string}>
     */
    private static function normalizeActions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $scope = trim((string) ($row['scope'] ?? ''));
            if ($name === '' || !preg_match(self::ACTION_NAME_PATTERN, $name)) {
                throw new RuntimeException('parent_bridge.actions: invalid action name');
            }
            if ($scope === '' || !AppBridgeScope::isValid($scope)) {
                throw new RuntimeException("parent_bridge.actions: invalid scope for action {$name}");
            }
            if (isset($seen[$name])) {
                throw new RuntimeException("parent_bridge.actions: duplicate action {$name}");
            }

            $seen[$name] = true;
            $description = trim((string) ($row['description'] ?? ''));

            $out[] = [
                'name' => $name,
                'scope' => $scope,
                'description' => $description !== '' ? mb_substr($description, 0, 255) : '',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, scope: string}>
     */
    private static function normalizeEvents(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $scope = trim((string) ($row['scope'] ?? ''));
            if ($name === '' || !preg_match(self::EVENT_NAME_PATTERN, $name)) {
                throw new RuntimeException('parent_bridge.events: invalid event name');
            }
            if ($scope === '' || !AppBridgeScope::isValid($scope)) {
                throw new RuntimeException("parent_bridge.events: invalid scope for event {$name}");
            }
            if (isset($seen[$name])) {
                throw new RuntimeException("parent_bridge.events: duplicate event {$name}");
            }

            $seen[$name] = true;
            $out[] = [
                'name' => $name,
                'scope' => $scope,
            ];
        }

        return $out;
    }
}
