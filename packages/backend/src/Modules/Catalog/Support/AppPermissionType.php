<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

final class AppPermissionType
{
    public const TEST = 'test';
    public const MANAGE = 'manage';

    /** @var list<string> */
    public const ALL = [
        self::TEST,
        self::MANAGE,
    ];

    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::ALL, true);
    }

    /** test or manage may open draft apps. */
    public static function canAccessDraft(string $permission): bool
    {
        return $permission === self::TEST || $permission === self::MANAGE;
    }
}
