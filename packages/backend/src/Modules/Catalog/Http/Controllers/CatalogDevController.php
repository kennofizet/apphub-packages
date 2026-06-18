<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppBundleStorageService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppHubService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppPublishService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CatalogDevController extends Controller
{
    public function __construct(
        private readonly AppHubService $appHub,
        private readonly AppCatalogService $catalog,
        private readonly AppBundleStorageService $bundles,
        private readonly AppPublishService $publish,
    ) {
    }

    public function apps(Request $request): JsonResponse
    {
        $this->guardDevUser($request);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', config('apphub.catalog_per_page', 24))));
        $result = $this->catalog->paginateForDev($page, $perPage);

        return $this->apiSuccessWithMeta($result['items'], $result['meta']);
    }

    public function updateStatus(Request $request, string $slug): JsonResponse
    {
        $userId = $this->guardDevUser($request);

        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', AppStatus::ALL),
        ]);

        try {
            $app = $this->appHub->setAppStatus($slug, $validated['status'], $userId);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'App not found' ? 404 : 422;

            return $this->apiErrorResponse($e->getMessage(), $status);
        }

        return $this->apiResponseWithContext($this->catalog->toCatalogItem($app, $userId, 'dev'));
    }

    public function readBundleFile(Request $request, string $slug): JsonResponse
    {
        $this->guardDevUser($request);

        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        $path = (string) $request->query('path', '');
        if ($path === '' || str_contains($path, '..')) {
            return $this->apiErrorResponse('Invalid file path', 422);
        }

        $app = App::query()->where('slug', $slug)->first();
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        $reviewBundle = $this->publish->resolveReviewBundle($app);
        if ($app->runtime_type !== AppRuntimeType::HOSTED || $reviewBundle === null) {
            return $this->apiErrorResponse('App has no hosted bundle', 422);
        }

        $compare = $request->boolean('compare');
        $baseline = $compare ? $this->publish->resolveBaselineBundle($app) : null;
        $changeStatus = 'added';

        try {
            $read = $this->bundles->readBundleTextFile($reviewBundle['path'], $path);
        } catch (\RuntimeException $e) {
            if ($baseline !== null) {
                try {
                    $oldRead = $this->bundles->readBundleTextFile($baseline['path'], $path);

                    return $this->apiResponseWithContext([
                        'slug' => $app->slug,
                        'path' => $path,
                        'content' => '',
                        'old_content' => $oldRead['content'],
                        'change_status' => 'deleted',
                        'truncated' => false,
                        'old_truncated' => $oldRead['truncated'],
                        'size' => 0,
                    ]);
                } catch (\RuntimeException) {
                    return $this->apiErrorResponse($e->getMessage(), 422);
                }
            }

            return $this->apiErrorResponse($e->getMessage(), 422);
        }

        $oldContent = null;
        $oldTruncated = false;
        if ($baseline !== null) {
            try {
                $oldRead = $this->bundles->readBundleTextFile($baseline['path'], $path);
                $oldContent = $oldRead['content'];
                $oldTruncated = $oldRead['truncated'];
                $changeStatus = $oldRead['content'] === $read['content'] ? 'unchanged' : 'modified';
            } catch (\RuntimeException) {
                $changeStatus = 'added';
            }
        }

        return $this->apiResponseWithContext([
            'slug' => $app->slug,
            'path' => $path,
            'content' => $read['content'],
            'old_content' => $oldContent,
            'change_status' => $changeStatus,
            'truncated' => $read['truncated'],
            'old_truncated' => $oldTruncated,
            'size' => $read['size'],
            'baseline_version' => $baseline['version'] ?? null,
        ]);
    }

    public function inspectBundle(Request $request, string $slug): JsonResponse
    {
        $this->guardDevUser($request);

        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        $app = App::query()->where('slug', $slug)->first();
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        $reviewBundle = $this->publish->resolveReviewBundle($app);
        if ($app->runtime_type !== AppRuntimeType::HOSTED || $reviewBundle === null) {
            return $this->apiErrorResponse('App has no hosted bundle', 422);
        }

        $baseline = $this->publish->resolveBaselineBundle($app);
        $fileEntries = $this->bundles->compareBundleTrees(
            $reviewBundle['path'],
            $baseline['path'] ?? null,
        );
        $maxFiles = 500;
        $truncated = count($fileEntries) > $maxFiles;
        $fileEntries = array_slice($fileEntries, 0, $maxFiles);

        return $this->apiResponseWithContext([
            'slug' => $app->slug,
            'name' => $app->name,
            'status' => $app->status,
            'runtime_type' => $app->runtime_type,
            'version' => $reviewBundle['version'],
            'live_version' => $app->version,
            'baseline_version' => $baseline['version'] ?? null,
            'pending_version' => $app->pending_version,
            'bundle_hash' => $reviewBundle['hash'],
            'bundle_entry' => $reviewBundle['entry'],
            'file_count' => count($fileEntries),
            'file_entries' => $fileEntries,
            'files' => array_column($fileEntries, 'path'),
            'files_truncated' => $truncated,
            'has_baseline' => $baseline !== null,
        ]);
    }

    public function rejectPending(Request $request, string $slug): JsonResponse
    {
        $userId = $this->guardDevUser($request);

        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        $app = App::query()->where('slug', $slug)->first();
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        try {
            $app = $this->publish->rejectDevReview($app);
        } catch (\RuntimeException $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        }

        return $this->apiResponseWithContext($this->catalog->toCatalogItem($app, $userId, 'dev'));
    }

    public function disable(Request $request, string $slug): JsonResponse
    {
        $userId = $this->guardDevUser($request);

        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        try {
            $app = $this->appHub->disableApp($slug, $userId);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'App not found' ? 404 : 403;

            return $this->apiErrorResponse($e->getMessage(), $status);
        }

        return $this->apiResponseWithContext($this->catalog->toCatalogItem($app, $userId, 'dev'));
    }

    private function guardDevUser(Request $request): int
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            throw new HttpException(401, 'Authentication required');
        }

        if (!$this->appHub->isDevUser($userId)) {
            throw new HttpException(403, 'Forbidden');
        }

        return $userId;
    }
}
