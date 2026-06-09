<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CatalogDevController extends Controller
{
    public function __construct(
        private readonly AppHubService $appHub,
        private readonly AppCatalogService $catalog,
    ) {
    }

    public function apps(Request $request): JsonResponse
    {
        $this->guardDevUser($request);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', config('apphub.catalog_per_page', 24))));
        $result = $this->catalog->paginateForDev($page, $perPage);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    public function updateStatus(Request $request, string $slug): JsonResponse
    {
        $userId = $this->guardDevUser($request);

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', AppStatus::ALL),
        ]);

        try {
            $app = $this->appHub->setAppStatus($slug, $validated['status'], $userId);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'App not found' ? 404 : 422;

            return response()->json(['success' => false, 'error' => $e->getMessage()], $status);
        }

        return response()->json([
            'success' => true,
            'data' => $this->catalog->toCatalogItem($app),
        ]);
    }

    public function disable(Request $request, string $slug): JsonResponse
    {
        $userId = $this->guardDevUser($request);

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug'], 422);
        }

        try {
            $app = $this->appHub->disableApp($slug, $userId);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'App not found' ? 404 : 403;

            return response()->json(['success' => false, 'error' => $e->getMessage()], $status);
        }

        return response()->json([
            'success' => true,
            'data' => $this->catalog->toCatalogItem($app),
        ]);
    }

    private function guardDevUser(Request $request): int
    {
        $userId = (int) $request->attributes->get('knf_core_user_id');
        if ($userId <= 0) {
            throw new HttpException(401, 'Authentication required');
        }

        if (!$this->appHub->isDevUser($userId)) {
            throw new HttpException(403, 'Forbidden');
        }

        return $userId;
    }
}
