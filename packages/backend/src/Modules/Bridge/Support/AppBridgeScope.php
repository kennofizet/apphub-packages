<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Support;

final class AppBridgeScope
{
    public const USER_READ = 'user.read';

    public const USER_PROFILE = 'user.profile';

    public const DESKTOP_NOTIFY = 'desktop.notify';

    public const DESKTOP_MESSAGE = 'desktop.message';

    public const DESKTOP_BADGE = 'desktop.badge';

    /** @var list<string> */
    public const ALL = [
        self::USER_READ,
        self::USER_PROFILE,
        self::DESKTOP_NOTIFY,
        self::DESKTOP_MESSAGE,
        self::DESKTOP_BADGE,
    ];

    public static function isValid(string $scope): bool
    {
        return in_array($scope, self::ALL, true);
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @return list<string>
     */
    public static function fromManifest(?array $manifest): array
    {
        if ($manifest === null) {
            return [];
        }

        return self::normalizeList($manifest['permissions'] ?? null);
    }

    /**
     * @return list<string>
     */
    public static function normalizeList(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $scopes = [];

        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (!is_array($raw)) {
            return [];
        }

        foreach ($raw as $item) {
            $scope = null;

            if (is_string($item)) {
                $scope = trim($item);
            } elseif (is_array($item) && isset($item['scope']) && is_string($item['scope'])) {
                $scope = trim($item['scope']);
            }

            if ($scope === null || $scope === '' || !self::isValid($scope)) {
                continue;
            }

            if (!in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }
}
