<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

final class ParentBridgeRegistry
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        /** @var array<string, mixed> $merged */
        $merged = config('apphub-parent-bridge', []);
        self::$config = $merged;

        return self::$config;
    }

    public static function isEnabled(): bool
    {
        return filter_var(self::config()['enabled'] ?? true, FILTER_VALIDATE_BOOL);
    }

  /**
   * @return array<string, mixed>|null
   */
    public static function action(string $name): ?array
    {
        $key = self::normalizeKey($name);
        $actions = self::config()['actions'] ?? [];
        if (!is_array($actions) || !isset($actions[$key]) || !is_array($actions[$key])) {
            return null;
        }

        return $actions[$key];
    }

  /**
   * @return array<string, mixed>|null
   */
    public static function event(string $name): ?array
    {
        $key = self::normalizeKey($name);
        $events = self::config()['events'] ?? [];
        if (!is_array($events) || !isset($events[$key]) || !is_array($events[$key])) {
            return null;
        }

        return $events[$key];
    }

    public static function maxArgsBytes(): int
    {
        $defaults = self::config()['defaults'] ?? [];

        return max(1024, (int) ($defaults['max_args_bytes'] ?? 65_536));
    }

    /**
     * @return array<string, mixed>
     */
    public static function security(): array
    {
        $security = self::config()['security'] ?? [];

        return is_array($security) ? $security : [];
    }

    public static function requireActiveSession(): bool
    {
        $configured = self::security()['require_active_session'] ?? null;
        if ($configured !== null) {
            return filter_var($configured, FILTER_VALIDATE_BOOL);
        }

        return !in_array((string) config('app.env', 'production'), ['local', 'testing'], true);
    }

    public static function requirePermissionInProduction(): bool
    {
        $configured = self::security()['require_permission_in_production'] ?? null;
        if ($configured !== null) {
            return filter_var($configured, FILTER_VALIDATE_BOOL);
        }

        return !in_array((string) config('app.env', 'production'), ['local', 'testing'], true);
    }

    public static function auditLogEnabled(): bool
    {
        return filter_var(self::security()['audit_log'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return list<string>
     */
    public static function allowedHandlerNamespaces(): array
    {
        $raw = self::security()['allowed_handler_namespaces'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }

    public static function rateLimitForAction(string $action): int
    {
        $config = self::action($action);
        if (is_array($config) && isset($config['rate_limit_per_minute'])) {
            return max(1, (int) $config['rate_limit_per_minute']);
        }

        $defaults = self::config()['defaults'] ?? [];

        return max(1, (int) ($defaults['rate_limit_per_minute'] ?? 60));
    }

    public static function rateLimitForEvent(string $event): int
    {
        $config = self::event($event);
        if (is_array($config) && isset($config['rate_limit_per_minute'])) {
            return max(1, (int) $config['rate_limit_per_minute']);
        }

        $defaults = self::config()['defaults'] ?? [];

        return max(1, (int) ($defaults['rate_limit_per_minute'] ?? 60));
    }

    /**
     * Demo payload for an action — action.demo_data overrides defaults.demo_data[name].
     *
     * @return mixed
     */
    public static function demoDataFor(string $name): mixed
    {
        $key = self::normalizeKey($name);
        $entry = self::action($key);
        if (is_array($entry) && array_key_exists('demo_data', $entry)) {
            return $entry['demo_data'];
        }

        $defaults = self::config()['defaults'] ?? [];
        $demoDefaults = $defaults['demo_data'] ?? [];
        if (!is_array($demoDefaults) || !isset($demoDefaults[$key])) {
            return null;
        }

        return $demoDefaults[$key];
    }

    /**
     * Install-dialog prompts for parent.* scopes — host config is source of truth.
     *
     * @return array<string, string> scope => user_prompt template ({app}, {scope})
     */
    public static function scopePrompts(): array
    {
        $raw = self::config()['scopes'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $scope => $meta) {
            if (!is_string($scope) || $scope === '' || !is_array($meta)) {
                continue;
            }
            $prompt = $meta['user_prompt'] ?? null;
            if (!is_string($prompt) || trim($prompt) === '') {
                continue;
            }
            $out[$scope] = trim($prompt);
        }

        return $out;
    }

    private static function normalizeKey(string $name): string
    {
        return strtolower(trim($name));
    }
}
