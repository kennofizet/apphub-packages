<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;
use RuntimeException;

class CatalogVersionController extends Controller
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppVersionService $versions,
    ) {
    }

    public function index(Request $request, string $slug): JsonResponse
    {
        $userId = (int) $request->attributes->get('knf_core_user_id');
        if ($userId <= 0) {
            return response()->json(['success' => false, 'error' => 'Authentication required'], 401);
        }

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug'], 422);
        }

        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return response()->json(['success' => false, 'error' => 'App not found'], 404);
        }

        try {
            $items = $this->versions->listForApp($app, $userId);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $app->slug,
                'current_version' => $app->version,
                'versions' => $items,
            ],
        ]);
    }
}
