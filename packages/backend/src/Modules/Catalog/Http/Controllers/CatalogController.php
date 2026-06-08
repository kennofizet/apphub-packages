<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\PackagesCore\Models\User;

class CatalogController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->resolveSessionUser($request),
                'installed' => [],
                'catalog' => [],
            ],
        ]);
    }

    public function apps(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', config('apphub.catalog_per_page', 24))));

        return response()->json([
            'success' => true,
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => $perPage],
        ]);
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
