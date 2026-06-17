<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Services;

use Kennofizet\AppHub\Modules\Bridge\Models\AppBridgeConsent;
use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;

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
