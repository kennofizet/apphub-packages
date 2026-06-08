<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

final class AppStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';

    /** @var list<string> */
    public const ALL = [
        self::DRAFT,
        self::ACTIVE,
        self::DISABLED,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    /** Draft (test permission) or active (zone RBAC) may launch; disabled may not. */
    public static function canLaunch(string $status): bool
    {
        return $status === self::DRAFT || $status === self::ACTIVE;
    }

    /** Visible in App Store for zone users without explicit test permission. */
    public static function isStoreListed(string $status): bool
    {
        return $status === self::ACTIVE;
    }

    /** Draft apps require app_permissions (test/manage) for catalog and launch. */
    public static function requiresTestPermission(string $status): bool
    {
        return $status === self::DRAFT;
    }

    public static function isDisabled(string $status): bool
    {
        return $status === self::DISABLED;
    }
}
