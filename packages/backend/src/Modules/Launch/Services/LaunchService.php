<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeDemoFixtures;
use Kennofizet\AppHub\Modules\Bridge\Support\ParentBridgeManifest;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppRuntimeServeService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;

final class LaunchService
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly LaunchTokenService $launchTokens,
        private readonly AppUsageService $usage,
        private readonly AppRuntimeServeService $runtimeServe,
        private readonly AppVersionService $versions,
        private readonly AppBridgeConsentService $bridgeConsents,
        private readonly AppEntryUrlGuard $entryUrlGuard,
        private readonly AppHealthcheckService $healthcheck,
    ) {
    }

    /**
     * @return array{
     *     slug: string,
     *     runtime_type: string,
     *     runtime_url: string|null,
     *     entry_url: string|null,
     *     launch_token: string,
     *     session_id: string,
     *     bundle_version: string|null,
     *     scopes_granted: list<string>,
     *     expires_in: int,
     *     parent_bridge: array{available: bool, actions: list<array<string, mixed>>, events: list<array<string, mixed>>},
     *     parent_bridge_demo: array<string, mixed>
     * }
     */
    public function launch(
        string $slug,
        int $userId,
        array $userZoneIds,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $bundleVersion = null,
    ): array {
        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            throw new LaunchDeniedException('App not found', 404);
        }

        if ($app->isDisabled()) {
            throw new LaunchDeniedException('App has been disabled', 403);
        }

        if (!AppStatus::canLaunch((string) $app->status)) {
            throw new LaunchDeniedException('App is not available for launch', 403);
        }

        if (!$this->catalog->userCanLaunch($app, $userId, $userZoneIds)) {
            throw new LaunchDeniedException('You do not have permission to launch this app', 403);
        }

        if ($this->healthcheck->isStale($app)) {
            $this->healthcheck->pingAndPersist($app);
        }

        $pinnedVersion = $this->normalizeBundleVersion($bundleVersion);
        if ($pinnedVersion !== null && $this->versions->resolveLaunchBundle($app, $pinnedVersion, $userId) === null) {
            throw new LaunchDeniedException('Requested app version is not available', 404);
        }

        $minted = $this->launchTokens->mint(
            $app,
            $userId,
            $ip,
            $userAgent,
            $pinnedVersion,
            $this->bridgeConsents->scopesForLaunch($app, $userId, $pinnedVersion),
        );
        $this->usage->log($userId, $app, AppUsageService::ACTION_APP_OPEN);

        $this->entryUrlGuard->assertLaunchable($app);

        $entryUrl = $this->resolveEntryUrl($app, $pinnedVersion, $userId);

        return [
            'slug' => $app->slug,
            'runtime_type' => (string) $app->runtime_type,
            'runtime_url' => $entryUrl,
            'entry_url' => $entryUrl,
            'launch_token' => $minted['launch_token'],
            'session_id' => $minted['session_id'],
            'bundle_version' => $pinnedVersion,
            'scopes_granted' => $minted['scopes_granted'],
            'expires_in' => $minted['expires_in'],
            'parent_bridge' => ParentBridgeManifest::catalogFromManifest(
                is_array($app->manifest) ? $app->manifest : null,
            ),
            'parent_bridge_demo' => ParentBridgeDemoFixtures::forManifest(
                is_array($app->manifest) ? $app->manifest : null,
            ),
        ];
    }

    /**
     * Extend an open launch session with a new short-lived launch_token (Hub Core auth required).
     *
     * @return array{
     *     slug: string,
     *     launch_token: string,
     *     session_id: string,
     *     scopes_granted: list<string>,
     *     expires_in: int,
     *     bundle_version: string|null
     * }
     */
    public function refresh(
        string $slug,
        int $userId,
        array $userZoneIds,
        string $sessionId,
        ?string $ip = null,
        ?string $userAgent = null,
    ): array {
        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            throw new LaunchDeniedException('App not found', 404);
        }

        if ($app->isDisabled()) {
            throw new LaunchDeniedException('App has been disabled', 403);
        }

        if (!AppStatus::canLaunch((string) $app->status)) {
            throw new LaunchDeniedException('App is not available for launch', 403);
        }

        if (!$this->catalog->userCanLaunch($app, $userId, $userZoneIds)) {
            throw new LaunchDeniedException('You do not have permission to launch this app', 403);
        }

        $record = $this->launchTokens->findSessionForRefresh($userId, (string) $app->slug, $sessionId);
        if ($record === null) {
            throw new LaunchDeniedException('Launch session expired — reopen the app', 401);
        }

        $bundleVersion = $record->bundle_version !== null ? trim((string) $record->bundle_version) : '';
        $pinnedVersion = $bundleVersion !== '' ? $this->normalizeBundleVersion($bundleVersion) : null;

        $refreshed = $this->launchTokens->refreshForUser(
            $app,
            $userId,
            $sessionId,
            $this->bridgeConsents->scopesForLaunch($app, $userId, $pinnedVersion),
            $ip,
            $userAgent,
        );
        if ($refreshed === null) {
            throw new LaunchDeniedException('Launch session expired — reopen the app', 401);
        }

        return [
            'slug' => $app->slug,
            'launch_token' => $refreshed['launch_token'],
            'session_id' => $refreshed['session_id'],
            'scopes_granted' => $refreshed['scopes_granted'],
            'expires_in' => $refreshed['expires_in'],
            'bundle_version' => $refreshed['bundle_version'],
        ];
    }

    private function resolveEntryUrl(App $app, ?string $bundleVersion, int $userId): ?string
    {
        if ($app->runtime_type === AppRuntimeType::HOSTED) {
            $bundle = $this->versions->resolveLaunchBundle($app, $bundleVersion, $userId);
            if ($bundle === null) {
                return null;
            }

            return $this->runtimeServe->buildRuntimeIndexUrl($app, null, $bundle['entry']);
        }

        return $app->entry_url;
    }

    private function normalizeBundleVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $normalized = AppSemver::normalize($version);

        return $normalized !== '' && AppSemver::isValid($normalized) ? $normalized : null;
    }
}
