<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

/** GET /apps ?mode= — which apps to list (server-filtered, cursor-paginated). */
final class AppCatalogMode
{
    /** Active apps for the current zone (main App Store). */
    public const STORE = 'store';

    /** Draft apps the user may test (Draft App Store). */
    public const DRAFT = 'draft';

    /** @var list<string> */
    public const ALL = [
        self::STORE,
        self::DRAFT,
    ];

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::ALL, true);
    }

    public static function normalize(?string $mode): string
    {
        $value = is_string($mode) ? strtolower(trim($mode)) : '';

        return self::isValid($value) ? $value : self::STORE;
    }
}
