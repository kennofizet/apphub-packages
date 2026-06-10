<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

final class AppSemver
{
    public static function isValid(string $version): bool
    {
        return (bool) preg_match(
            '/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$/',
            trim($version),
        );
    }

    public static function isGreaterThan(string $next, string $current): bool
    {
        return version_compare(self::normalize($next), self::normalize($current), '>');
    }

    public static function normalize(string $version): string
    {
        return trim($version);
    }
}
