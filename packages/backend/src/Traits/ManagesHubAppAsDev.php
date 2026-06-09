<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Traits;

use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use RuntimeException;

/**
 * Dev-only helpers for host User models listed in apphub.dev_user_ids.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ManagesHubAppAsDev
{
    public function isAppHubDev(): bool
    {
        return $this->appHubService()->isDevUser($this->appHubActorId());
    }

    public function hubDisableApp(string $slug): App
    {
        $this->assertAppHubDev();

        return $this->appHubService()->disableApp($slug, $this->appHubActorId());
    }

    public function hubSetAppStatus(string $slug, string $status): App
    {
        $this->assertAppHubDev();

        return $this->appHubService()->setAppStatus($slug, $status, $this->appHubActorId());
    }

    protected function appHubService(): AppHubService
    {
        return app(AppHubService::class);
    }

    protected function appHubActorId(): int
    {
        return (int) $this->getKey();
    }

    protected function assertAppHubDev(): void
    {
        if (!$this->isAppHubDev()) {
            throw new RuntimeException('User is not an App Hub dev (check APPHUB_DEV_USER_IDS).');
        }
    }
}
