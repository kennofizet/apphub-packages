<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

final class AppVersionReviewStatus
{
    public const PENDING = 'pending';
    public const PUBLISHED = 'published';
    public const REJECTED = 'rejected';
    /** Superseded by a newer upload or resolved when another version was approved/rejected. */
    public const SKIPPED = 'skipped';

    public const ALL = [
        self::PENDING,
        self::PUBLISHED,
        self::REJECTED,
        self::SKIPPED,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }
}
