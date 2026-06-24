<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Launch\Models\AppUsageLog;

final class AppUsageService
{
    public const ACTION_APP_OPEN = 'app_open';
    public const ACTION_ERROR = 'error';

    /** @var list<string> */
    public const ALLOWED_ACTIONS = [
        self::ACTION_APP_OPEN,
        self::ACTION_ERROR,
    ];

    public function __construct(private readonly AppCatalogService $catalog)
    {
    }

    public function log(int $userId, App $app, string $action, ?array $metadata = null): void
    {
        AppUsageLog::query()->create([
            'app_id' => $app->id,
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function logBySlug(
        int $userId,
        string $slug,
        string $action,
        array $userZoneIds,
        ?array $metadata = null,
    ): void {
        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            throw new LaunchDeniedException('App not found', 404);
        }

        if (!$this->catalog->userCanLaunch($app, $userId, $userZoneIds)) {
            throw new LaunchDeniedException('You do not have permission to log usage for this app', 403);
        }

        $this->log($userId, $app, $action, self::sanitizeMetadata($metadata));
    }

    /** @return array<string, mixed>|null */
    public static function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $maxBytes = max(256, (int) config('apphub.usage_report_max_bytes', 4096));
        $encoded = json_encode($metadata, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > $maxBytes) {
            return [
                'message' => 'Metadata truncated — exceeded size limit',
                'truncated' => true,
            ];
        }

        return $metadata;
    }
}
