<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;

final class LaunchService
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly LaunchTokenService $launchTokens,
        private readonly AppUsageService $usage,
    ) {
    }

    /**
     * @return array{
     *     slug: string,
     *     runtime_url: string|null,
     *     entry_url: string|null,
     *     launch_token: string,
     *     session_id: string,
     *     scopes_granted: list<string>
     * }
     */
    public function launch(
        string $slug,
        int $userId,
        ?int $currentZoneId,
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

        if (!$this->catalog->userCanLaunch($app, $userId, $currentZoneId)) {
            throw new LaunchDeniedException('You do not have permission to launch this app', 403);
        }

        $minted = $this->launchTokens->mint($app, $userId, $ip, $userAgent);
        $this->usage->log($userId, $app, AppUsageService::ACTION_APP_OPEN);

        return [
            'slug' => $app->slug,
            'runtime_url' => $app->entry_url,
            'entry_url' => $app->entry_url,
            'launch_token' => $minted['launch_token'],
            'session_id' => $minted['session_id'],
            'scopes_granted' => $minted['scopes_granted'],
        ];
    }
}
