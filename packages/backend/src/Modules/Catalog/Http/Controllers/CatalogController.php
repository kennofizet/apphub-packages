<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;
use Kennofizet\PackagesCore\Models\User;

class CatalogController extends Controller
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppHubService $appHub,
    ) {
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $userId = (int) $request->attributes->get('knf_core_user_id');
        $zoneId = $this->currentZoneId($request);
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->resolveSessionUser($request),
                'installed' => [],
                'is_dev_user' => $this->appHub->isDevUser($userId),
                'zone_id' => $zoneId,
            ],
        ]);
    }

    public function apps(Request $request): JsonResponse
    {
        $userId = (int) $request->attributes->get('knf_core_user_id');
        $zoneId = $this->currentZoneId($request);
        $mode = AppCatalogMode::normalize($request->query('mode'));
        $perPage = min(50, max(1, (int) $request->query('per_page', config('apphub.catalog_per_page', 24))));
        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

        $result = $this->catalog->cursorPaginateForUser($userId, $zoneId, $mode, $cursor, $perPage);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    private function currentZoneId(Request $request): ?int
    {
        $zoneId = $request->attributes->get('knf_core_user_zone_id_current');

        return $zoneId !== null && $zoneId !== '' ? (int) $zoneId : null;
    }

    /** @return array{id: int, name: string}|null */
    private function resolveSessionUser(Request $request): ?array
    {
        $userId = $request->attributes->get('knf_core_user_id');
        if (empty($userId)) {
            return null;
        }

        $user = User::byId((int) $userId)->first();
        if ($user === null) {
            return ['id' => (int) $userId, 'name' => (string) $userId];
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
