<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppPublishService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;
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
        $userId = self::currentUserId();
        if ($userId === null) {
            return $this->apiErrorResponse('Authentication required', 401);
        }

        $meta = null;

        try {
            if ($request->hasFile('bundle')) {
                $request->validate([
                    'bundle' => 'required|file|mimes:zip|max:51200',
                ]);

                $zip = $request->file('bundle');
                if ($zip === null) {
                    return $this->apiErrorResponse('Bundle zip is required', 422);
                }

                $meta = $this->manifests->fromZip($zip);
                $app = $this->publish->registerHosted($userId, $meta, $zip);
            } else {
                $payload = $request->all();
                if (!is_array($payload) || $payload === []) {
                    return $this->apiErrorResponse(
                        'Send multipart bundle zip (hosted) or JSON manifest with entry_url (iframe)',
                        422,
                    );
                }

                $meta = $this->manifests->normalizeIframe($payload);
                $app = $this->publish->registerIframe($userId, $meta);
            }
        } catch (RuntimeException $e) {
            $extra = [];
            if (is_array($meta) && isset($meta['slug'])) {
                $extra['slug'] = $meta['slug'];
            }
            if (is_array($meta) && isset($meta['version'])) {
                $extra['version'] = $meta['version'];
            }

            return response()->json($this->apiErrorPayload($e->getMessage(), $extra), 422);
        }

        return $this->apiResponseWithContext(
            $this->catalog->toCatalogItem($app, $userId, AppCatalogMode::PUBLISHER),
            201,
        );
    }
}
