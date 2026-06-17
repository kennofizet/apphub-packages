<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubPublicUrlService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;
use Kennofizet\PackagesCore\Models\User;

class CatalogController extends Controller
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppHubService $appHub,
        private readonly AppHubPublicUrlService $publicUrls,
    ) {
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $userId = (int) (self::currentUserId() ?? 0);

        return $this->apiResponseWithContext([
            'user' => $this->resolveSessionUser(),
            'installed' => [],
            'is_dev_user' => $this->appHub->isDevUser($userId),
            'zone_id' => self::currentZoneId(),
            'origins' => $this->bootstrapOrigins($request),
        ]);
    }

    public function apps(Request $request): JsonResponse
    {
        $userId = (int) (self::currentUserId() ?? 0);
        $mode = AppCatalogMode::normalize($request->query('mode'));
        $perPage = min(50, max(1, (int) $request->query('per_page', config('apphub.catalog_per_page', 24))));
        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

        $result = $this->catalog->cursorPaginateForUser($userId, self::currentZoneId(), $mode, $cursor, $perPage);

        return $this->apiSuccessWithMeta($result['items'], $result['meta']);
    }

    /** @return array{hub_public_url: string, frontend_origin: string, runtime_public_url: string, auto_derived: true} */
    private function bootstrapOrigins(Request $request): array
    {
        return [
            'hub_public_url' => $this->publicUrls->hubPublicUrlFromConfig(),
            'frontend_origin' => $this->publicUrls->resolveHubPublicUrl($request),
            'runtime_public_url' => $this->publicUrls->apiBaseUrl(),
            'auto_derived' => true,
        ];
    }

    /** @return array{id: int, name: string}|null */
    private function resolveSessionUser(): ?array
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            return null;
        }

        $user = User::byId($userId)->first();
        if ($user === null) {
            return ['id' => $userId, 'name' => (string) $userId];
        }

        $nameCol = $user->getNameColumn();
        $name = ($nameCol && isset($user->{$nameCol}))
            ? (string) $user->{$nameCol}
            : (string) $user->id;

        return [
            'id' => (int) $user->id,
            'name' => $name,
        ];
    }
}
