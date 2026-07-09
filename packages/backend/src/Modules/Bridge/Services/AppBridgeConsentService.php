<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Services;

use Illuminate\Support\Collection;
use Kennofizet\AppHub\Modules\Bridge\Models\AppBridgeConsent;
use Kennofizet\AppHub\Modules\Bridge\Models\AppBridgeParentConsent;
use Kennofizet\AppHub\Modules\Bridge\Models\AppBridgeParentVersionApproval;
use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppZoneAccess;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;
use Kennofizet\PackagesCore\Models\ZoneUser;

final class AppBridgeConsentService
{
    public function __construct(
        private readonly AppVersionService $versions,
    ) {
    }

    /**
     * Record install/update consent: basic scopes immediately; parent scopes stored per version (DEV gate on launch).
     *
     * @return list<string> scopes recorded (basic + parent user-ack)
     */
    public function recordManifestConsents(App $app, int $userId, ?string $bundleVersion): array
    {
        $manifestScopes = $this->versions->permissionsForLaunchBundle($app, $bundleVersion);
        $effectiveVersion = $this->resolveBundleVersion($app, $bundleVersion);
        [$basicScopes, $parentScopes] = $this->splitScopes($manifestScopes);

        foreach ($basicScopes as $scope) {
            AppBridgeConsent::query()->updateOrCreate(
                [
                    'app_id' => $app->id,
                    'user_id' => $userId,
                    'scope' => $scope,
                ],
                [],
            );
        }

        $this->pruneBasicConsentsNotInManifest($app, $userId, $basicScopes);
        $this->purgeLegacyParentRowsFromBasicTable($app, $userId);

        foreach ($parentScopes as $scope) {
            AppBridgeParentConsent::query()->updateOrCreate(
                [
                    'app_id' => $app->id,
                    'user_id' => $userId,
                    'scope' => $scope,
                    'bundle_version' => $effectiveVersion,
                ],
                [],
            );
        }

        $this->pruneParentConsentsNotInManifest($app, $userId, $effectiveVersion, $parentScopes);

        return AppBridgeScope::normalizeList(array_merge($basicScopes, $parentScopes));
    }

    /**
     * Scopes minted on launch token: basic from install consent; parent only when user acked + DEV approved version.
     *
     * @return list<string>
     */
    public function scopesForLaunch(App $app, int $userId, ?string $bundleVersion): array
    {
        $allowed = $this->versions->permissionsForLaunchBundle($app, $bundleVersion);
        if ($allowed === []) {
            return [];
        }

        $effectiveVersion = $this->resolveBundleVersion($app, $bundleVersion);
        [$basicAllowed, $parentAllowed] = $this->splitScopes($allowed);

        $basicAllowedSet = array_fill_keys($basicAllowed, true);
        $storedBasic = AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->pluck('scope')
            ->all();

        $granted = [];
        foreach ($storedBasic as $scope) {
            if (!is_string($scope)) {
                continue;
            }
            $scope = trim($scope);
            if ($scope !== '' && isset($basicAllowedSet[$scope])) {
                $granted[] = $scope;
            }
        }

        if ($parentAllowed !== [] && $this->isParentBridgeDevApproved($app, $effectiveVersion)) {
            $parentAllowedSet = array_fill_keys($parentAllowed, true);
            $storedParent = AppBridgeParentConsent::query()
                ->where('app_id', $app->id)
                ->where('user_id', $userId)
                ->where('bundle_version', $effectiveVersion)
                ->pluck('scope')
                ->all();

            foreach ($storedParent as $scope) {
                if (!is_string($scope)) {
                    continue;
                }
                $scope = trim($scope);
                if ($scope !== '' && isset($parentAllowedSet[$scope])) {
                    $granted[] = $scope;
                }
            }
        }

        return AppBridgeScope::normalizeList($granted);
    }

    public function userHasScope(App $app, int $userId, string $scope, ?string $bundleVersion = null): bool
    {
        if (!AppBridgeScope::isValid($scope)) {
            return false;
        }

        if (AppBridgeScope::isParentScope($scope)) {
            $effectiveVersion = $this->resolveBundleVersion($app, $bundleVersion);

            return $this->isParentBridgeDevApproved($app, $effectiveVersion)
                && $this->userHasParentScope($app, $userId, $scope, $effectiveVersion);
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

        $basic = AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->delete();

        $parent = AppBridgeParentConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->delete();

        return $basic + $parent;
    }

    /**
     * Drop all install consents for an app (every user).
     */
    public function revokeAllForApp(App $app): int
    {
        $basic = AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->delete();

        $parent = AppBridgeParentConsent::query()
            ->where('app_id', $app->id)
            ->delete();

        AppBridgeParentVersionApproval::query()
            ->where('app_id', $app->id)
            ->delete();

        return $basic + $parent;
    }

    /**
     * Draft re-submit: publisher must re-accept parent scopes for the new version only.
     */
    public function revokeParentConsentsForUserVersion(App $app, int $userId, string $bundleVersion): int
    {
        if ($userId < 1) {
            return 0;
        }

        $version = AppSemver::normalize($bundleVersion);
        if ($version === '') {
            return 0;
        }

        return AppBridgeParentConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->where('bundle_version', $version)
            ->delete();
    }

    public function revokeParentDevApprovalForVersion(App $app, string $bundleVersion): int
    {
        $version = AppSemver::normalize($bundleVersion);
        if ($version === '') {
            return 0;
        }

        return AppBridgeParentVersionApproval::query()
            ->where('app_id', $app->id)
            ->where('bundle_version', $version)
            ->delete();
    }

    /** DEV review: parent bridge scopes become live for this app version. */
    public function approveParentBridgeForVersion(App $app, string $bundleVersion, ?int $devUserId = null): void
    {
        $version = AppSemver::normalize($bundleVersion);
        if ($version === '') {
            return;
        }

        AppBridgeParentVersionApproval::query()->updateOrCreate(
            [
                'app_id' => $app->id,
                'bundle_version' => $version,
            ],
            [
                'approved_by_user_id' => $devUserId !== null && $devUserId > 0 ? $devUserId : null,
                'approved_at' => now(),
            ],
        );
    }

    public function isParentBridgeDevApproved(App $app, string $bundleVersion): bool
    {
        $version = AppSemver::normalize($bundleVersion);
        if ($version === '') {
            return false;
        }

        return AppBridgeParentVersionApproval::query()
            ->where('app_id', $app->id)
            ->where('bundle_version', $version)
            ->whereNotNull('approved_at')
            ->exists();
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
     * @param list<string> $manifestScopes
     * @return array{0: list<string>, 1: list<string>}
     */
    private function splitScopes(array $manifestScopes): array
    {
        $basic = [];
        $parent = [];

        foreach (AppBridgeScope::normalizeList($manifestScopes) as $scope) {
            if (AppBridgeScope::isParentScope($scope)) {
                $parent[] = $scope;
            } else {
                $basic[] = $scope;
            }
        }

        return [$basic, $parent];
    }

    private function resolveBundleVersion(App $app, ?string $bundleVersion): string
    {
        $version = $bundleVersion !== null ? trim($bundleVersion) : '';
        if ($version !== '') {
            $normalized = AppSemver::normalize($version);

            return $normalized !== '' ? $normalized : $version;
        }

        $fromApp = AppSemver::normalize((string) ($app->version ?? ''));

        return $fromApp !== '' ? $fromApp : trim((string) ($app->version ?? ''));
    }

    private function userHasParentScope(App $app, int $userId, string $scope, string $bundleVersion): bool
    {
        $version = AppSemver::normalize($bundleVersion);
        if ($version === '') {
            return false;
        }

        return AppBridgeParentConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->where('bundle_version', $version)
            ->where('scope', $scope)
            ->exists();
    }

    private function purgeLegacyParentRowsFromBasicTable(App $app, int $userId): void
    {
        AppBridgeConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->where('scope', 'like', 'parent.%')
            ->delete();
    }

    /**
     * @param list<string> $manifestScopes
     */
    private function pruneBasicConsentsNotInManifest(App $app, int $userId, array $manifestScopes): void
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

    /**
     * @param list<string> $manifestScopes
     */
    private function pruneParentConsentsNotInManifest(
        App $app,
        int $userId,
        string $bundleVersion,
        array $manifestScopes,
    ): void {
        $version = AppSemver::normalize($bundleVersion);
        if ($version === '') {
            return;
        }

        $query = AppBridgeParentConsent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->where('bundle_version', $version);

        if ($manifestScopes === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('scope', $manifestScopes)->delete();
    }
}
