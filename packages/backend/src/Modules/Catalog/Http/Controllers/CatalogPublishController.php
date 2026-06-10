<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppPublishService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestParser;
use RuntimeException;

class CatalogPublishController extends Controller
{
    public function __construct(
        private readonly AppPublishService $publish,
        private readonly AppCatalogService $catalog,
        private readonly AppManifestParser $manifests,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $userId = (int) $request->attributes->get('knf_core_user_id');
        if ($userId <= 0) {
            return response()->json(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $request->validate([
            'bundle' => 'required|file|mimes:zip|max:51200',
        ]);

        $zip = $request->file('bundle');
        if ($zip === null) {
            return response()->json(['success' => false, 'error' => 'Bundle zip is required'], 422);
        }

        $meta = null;
        try {
            $meta = $this->manifests->fromZip($zip);
            $app = $this->publish->registerHosted($userId, $meta, $zip);
        } catch (RuntimeException $e) {
            $payload = ['success' => false, 'error' => $e->getMessage()];
            if (is_array($meta) && isset($meta['slug'])) {
                $payload['slug'] = $meta['slug'];
            }
            if (is_array($meta) && isset($meta['version'])) {
                $payload['version'] = $meta['version'];
            }

            return response()->json($payload, 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->catalog->toCatalogItem($app),
        ], 201);
    }
}
