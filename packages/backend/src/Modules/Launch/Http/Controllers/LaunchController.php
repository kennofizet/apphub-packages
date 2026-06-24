<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckService;
use Kennofizet\AppHub\Modules\Launch\Services\AppLaunchCallerUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\AppUsageService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchDeniedException;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;

class LaunchController extends Controller
{
    public function __construct(
        private readonly LaunchService $launch,
        private readonly LaunchTokenService $launchTokens,
        private readonly AppUsageService $usage,
        private readonly AppCatalogService $catalog,
        private readonly AppHealthcheckService $healthcheck,
        private readonly AppLaunchCallerUrlGuard $callerUrlGuard,
    ) {
    }

    public function launch(Request $request, string $slug): JsonResponse
    {
        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'version' => 'nullable|string|max:64',
        ]);

        try {
            $data = $this->launch->launch(
                $slug,
                (int) self::currentUserId(),
                self::currentUserZoneIdList(),
                $request->ip(),
                (string) $request->userAgent(),
                $validated['version'] ?? null,
            );
        } catch (LaunchDeniedException $e) {
            return $this->apiErrorResponse($e->getMessage(), $e->httpStatus());
        }

        return $this->apiResponseWithContext($data);
    }

    /** Tool backend verifies launch token (no user session token required). */
    public function verifyLaunchToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'launch_token' => 'required|string|min:32|max:128',
            'app_slug' => 'nullable|string|regex:/^[a-z0-9][a-z0-9_-]{0,63}$/',
        ]);

        $record = $this->launchTokens->recordForGrant($validated['launch_token']);
        if ($record === null || $record->app === null) {
            return $this->apiErrorResponse('Invalid or expired launch token', 401);
        }

        $appSlug = $validated['app_slug'] ?? null;
        if ($appSlug !== null && $appSlug !== '' && $record->app->slug !== $appSlug) {
            return $this->apiErrorResponse('Invalid or expired launch token', 401);
        }

        $bundleVersion = $record->bundle_version !== null ? trim((string) $record->bundle_version) : null;
        if ($bundleVersion === '') {
            $bundleVersion = null;
        }

        $guard = $this->callerUrlGuard->validate($record->app, $bundleVersion, $request, [
            'user_id' => (int) $record->user_id,
            'app_slug' => $record->app->slug,
        ]);
        if ($guard['ok'] !== true) {
            return $this->apiErrorResponse($guard['error'], $guard['status']);
        }

        $result = $this->launchTokens->verify(
            $validated['launch_token'],
            $validated['app_slug'] ?? null,
        );

        if ($result === null) {
            return $this->apiErrorResponse('Invalid or expired launch token', 401);
        }

        return $this->apiResponseWithContext($result);
    }

    public function ping(Request $request, string $slug): JsonResponse
    {
        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $userId = (int) self::currentUserId();
        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        if (!$this->catalog->userCanLaunch($app, $userId, self::currentUserZoneIdList())) {
            return $this->apiErrorResponse('You do not have permission to test this app', 403);
        }

        return $this->apiResponseWithContext($this->healthcheck->pingAndPersist($app));
    }

    public function usage(Request $request, string $slug): JsonResponse
    {
        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'action' => 'required|string|in:' . implode(',', AppUsageService::ALLOWED_ACTIONS),
            'metadata' => 'nullable|array',
            'metadata.message' => 'nullable|string|max:2000',
            'metadata.name' => 'nullable|string|max:255',
            'metadata.stack' => 'nullable|string|max:4000',
        ]);

        $metadata = AppUsageService::sanitizeMetadata($validated['metadata'] ?? null);

        try {
            $this->usage->logBySlug(
                (int) self::currentUserId(),
                $slug,
                $validated['action'],
                self::currentUserZoneIdList(),
                $metadata,
            );
        } catch (LaunchDeniedException $e) {
            return $this->apiErrorResponse($e->getMessage(), $e->httpStatus());
        }

        return $this->apiResponseWithContext(['logged' => true]);
    }
}
