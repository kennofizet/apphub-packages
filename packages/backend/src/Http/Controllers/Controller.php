<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Http\Controllers;

use Kennofizet\AppHub\Core\Model\BaseModelResponse;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\PackagesCore\Core\Model\BaseModelActions;
use Kennofizet\PackagesCore\Traits\GlobalDataTrait;
use Illuminate\Http\JsonResponse;

/**
 * Shared controller base — reuses packages-core context helpers with App Hub JSON envelope.
 *
 * @see Kennofizet\ReleaseSupport\Controllers\Controller
 */
abstract class Controller
{
    use GlobalDataTrait, BaseModelActions;

    protected const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    /** Data-first success response (release-support style). */
    public function apiResponseWithContext(mixed $data = [], int $status = 200): JsonResponse
    {
        return response()->json(BaseModelResponse::success($data), $status);
    }

    /** App Hub errors use an `error` field (not packages-core `message` / `datas`). */
    public function apiErrorResponse(string $message = 'Error', int $status = 403, $data = null): JsonResponse
    {
        return response()->json(
            BaseModelResponse::error($message, is_array($data) ? $data : null),
            $status,
        );
    }

    protected function apiSuccessWithMeta(mixed $data, array $meta, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    /** @return array<string, mixed> */
    protected function apiErrorPayload(string $error, array $extra = []): array
    {
        return array_merge(BaseModelResponse::error($error), $extra);
    }

    protected function ensureAuthenticated(): ?JsonResponse
    {
        if (self::currentUserId() === null) {
            return $this->apiErrorResponse('Authentication required', 401);
        }

        return null;
    }

    protected function ensureValidSlug(string $slug): ?JsonResponse
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return $this->apiErrorResponse('Invalid app slug', 422);
        }

        return null;
    }

    protected static function currentZoneId(): ?int
    {
        $zoneId = self::currentUserZoneId();
        if ($zoneId === null || $zoneId === '') {
            return null;
        }

        return (int) $zoneId;
    }

    /**
     * All zone IDs the authenticated user belongs to (packages-core request context).
     *
     * @return list<int>
     */
    protected static function currentUserZoneIdList(): array
    {
        return AppCatalogService::normalizeUserZoneIds(self::currentUserZoneIds());
    }
}
