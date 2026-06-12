<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

/** GET /apps ?mode= — which apps to list (server-filtered, cursor-paginated). */
final class AppCatalogMode
{
    /** Active apps for the current zone (main App Store). */
    public const STORE = 'store';

    /** Draft apps the user may test (legacy; prefer publisher). */
    public const DRAFT = 'draft';

    /** Owner's draft + active apps (Publisher hub / My apps). */
    public const PUBLISHER = 'publisher';

    /** @var list<string> */
    public const ALL = [
        self::STORE,
        self::DRAFT,
        self::PUBLISHER,
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
