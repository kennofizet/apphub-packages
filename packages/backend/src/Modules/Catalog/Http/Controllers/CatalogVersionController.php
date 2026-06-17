<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
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
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        $userId = (int) self::currentUserId();
        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        try {
            $items = $this->versions->listForApp($app, $userId);
        } catch (RuntimeException $e) {
            return $this->apiErrorResponse($e->getMessage(), 403);
        }

        return $this->apiResponseWithContext([
            'slug' => $app->slug,
            'current_version' => $app->version,
            'pending_version' => $app->pending_version,
            'app_status' => $app->status,
            'versions' => $items,
        ]);
    }
}
