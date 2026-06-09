<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Traits;

use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;

/**
 * Host User model — list apps visible to this user for the current request zone.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasHubAppCatalog
{
    /** @return list<array<string, mixed>> */
    public function hubAppCatalog(?int $zoneId = null, int $limit = 24): array
    {
        $userId = (int) $this->getKey();
        $resolvedZoneId = $zoneId ?? $this->resolveHubZoneId();

        return $this->catalogService()->listForUser(
            $userId,
            $resolvedZoneId,
            AppCatalogMode::STORE,
            $limit,
        );
    }

    protected function catalogService(): AppCatalogService
    {
        return app(AppCatalogService::class);
    }

    protected function resolveHubZoneId(): ?int
    {
        $zoneId = request()->attributes->get('knf_core_user_zone_id_current');

        return $zoneId !== null && $zoneId !== '' ? (int) $zoneId : null;
    }
}
