<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppZoneAccess;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Kennofizet\PackagesCore\Models\Zone;
use RuntimeException;

final class AppHubService
{
    public function isDevUser(?int $userId = null): bool
    {
        if ($userId === null || $userId <= 0) {
            return false;
        }

        $devUserIds = array_map(
            static fn ($id): int => (int) $id,
            (array) config('apphub.dev_user_ids', []),
        );

        return in_array($userId, $devUserIds, true);
    }

    public function disableApp(string $slug, int $actorUserId): App
    {
        $this->assertDevUser($actorUserId);

        return $this->setAppStatus($slug, AppStatus::DISABLED);
    }

    public function setAppStatus(string $slug, string $status, ?int $actorUserId = null): App
    {
        if ($actorUserId !== null) {
            $this->assertDevUser($actorUserId);
        }

        if (!AppStatus::isValid($status)) {
            throw new RuntimeException('Invalid app status');
        }

        $app = App::query()->where('slug', $slug)->first();
        if ($app === null) {
            throw new RuntimeException('App not found');
        }

        $app->status = $status;
        $app->save();

        if ($status === AppStatus::ACTIVE) {
            $this->ensureDefaultZoneAccess($app);
        }

        return $app;
    }

    public function assertDevUser(int $userId): void
    {
        if (!$this->isDevUser($userId)) {
            throw new RuntimeException('User is not an App Hub dev (check APPHUB_DEV_USER_IDS).');
        }
    }

    private function ensureDefaultZoneAccess(App $app): void
    {
        if ($app->zoneAccess()->exists()) {
            return;
        }

        $zoneId = (int) (Zone::query()->orderBy('id')->value('id') ?? 0);
        if ($zoneId <= 0) {
            return;
        }

        AppZoneAccess::query()->updateOrCreate(
            [
                'app_id' => $app->id,
                'zone_id' => $zoneId,
            ],
            [],
        );
    }
}
