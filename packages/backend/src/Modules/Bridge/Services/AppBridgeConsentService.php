<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Services;

use Illuminate\Support\Collection;
use Kennofizet\AppHub\Modules\Bridge\Models\AppBridgeConsent;
use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppZoneAccess;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;
use Kennofizet\PackagesCore\Models\ZoneUser;

final class AppBridgeConsentService
{
    public function __construct(
        private readonly AppVersionService $versions,
    ) {
    }

    /**
     * Record install/update consent: all manifest permissions from server bundle (never from client).
     *
     * @return list<string> scopes recorded
     */
    public function recordManifestConsents(App $app, int $userId, ?string $bundleVersion): array
    {
        $manifestScopes = $this->versions->permissionsForLaunchBundle($app, $bundleVersion);
        foreach ($manifestScopes as $scope) {
            AppBridgeConsent::query()->updateOrCreate(
                [
                    'app_id' => $app->id,
                    'user_id' => $userId,
                    'scope' => $scope,
                ],
                [],
            );
        }

        $this->pruneConsentsNotInManifest($app, $userId, $manifestScopes);

        return $manifestScopes;
    }

    /**
     * Scopes minted on launch token: server consent ∩ manifest allowlist for bundle.
     *
     * @return list<string>
     */
    public function scopesForLaunch(App $app, int $userId, ?string $bundleVersion): array
    {
        $allowed = $this->versions->permissionsForLaunchBundle($app, $bundleVersion);
        if ($allowed === []) {
            return [];
        }

        $allowedSet = array_fill_keys($allowed, true);
        $stored = AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->pluck('scope')
            ->all();

        $granted = [];
        foreach ($stored as $scope) {
            if (!is_string($scope)) {
                continue;
            }
            $scope = trim($scope);
            if ($scope !== '' && isset($allowedSet[$scope])) {
                $granted[] = $scope;
            }
        }

        return AppBridgeScope::normalizeList($granted);
    }

    public function userHasScope(App $app, int $userId, string $scope): bool
    {
        if (!AppBridgeScope::isValid($scope)) {
            return false;
        }

        return AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->where('scope', $scope)
            ->exists();
    }

    public function userHasUserReadAccess(App $app, int $userId): bool
    {
        return $this->userHasScope($app, $userId, AppBridgeScope::USER_PROFILE)
            || $this->userHasScope($app, $userId, AppBridgeScope::USER_READ);
    }

    public function revokeAllForUser(App $app, int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }

        return AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * App IDs the user still receives desktop.notify for (install consent).
     *
     * @return list<int>
     */
    public function subscribedNotifyAppIdsForUser(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        return AppBridgeConsent::query()
            ->where('user_id', $userId)
            ->where('scope', AppBridgeScope::DESKTOP_NOTIFY)
            ->distinct()
            ->pluck('app_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * User IDs in app zones who installed and granted desktop.notify.
     *
     * @return Collection<int, int>
     */
    public function notifyRecipientUserIdsForApp(App $app): Collection
    {
        $zoneIds = AppZoneAccess::query()
            ->where('app_id', $app->id)
            ->pluck('zone_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($zoneIds->isEmpty()) {
            return collect();
        }

        $zoneUserIds = ZoneUser::query()
            ->whereIn('zone_id', $zoneIds->all())
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($zoneUserIds->isEmpty()) {
            return collect();
        }

        return AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('scope', AppBridgeScope::DESKTOP_NOTIFY)
            ->whereIn('user_id', $zoneUserIds->all())
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * Drop consent rows for permissions no longer declared in the installed bundle manifest.
     *
     * @param list<string> $manifestScopes
     */
    private function pruneConsentsNotInManifest(App $app, int $userId, array $manifestScopes): void
    {
        $query = AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId);

        if ($manifestScopes === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('scope', $manifestScopes)->delete();
    }
}
