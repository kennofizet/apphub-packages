<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

final class AppRuntimeType
{
    public const IFRAME = 'iframe';
    /** Hub-hosted frontend bundle (Vue/React/HTML dist) — sandbox iframe on Hub origin. */
    public const HOSTED = 'hosted';
    public const CONNECTED = 'connected';
    public const NATIVE = 'native';

    /** @var list<string> */
    public const ALL = [
        self::IFRAME,
        self::HOSTED,
        self::CONNECTED,
        self::NATIVE,
    ];

    public static function isValid(string $runtimeType): bool
    {
        return in_array($runtimeType, self::ALL, true);
    }
}
