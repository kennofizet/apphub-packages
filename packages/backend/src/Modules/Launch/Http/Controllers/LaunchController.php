<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckService;
use Kennofizet\AppHub\Modules\Launch\Services\AppLaunchCallerUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\AppUsageService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchDeniedException;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;

class LaunchController extends Controller
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

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
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug'], 422);
        }

        $userId = $request->attributes->get('knf_core_user_id');
        if (empty($userId)) {
            return response()->json(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $validated = $request->validate([
            'version' => 'nullable|string|max:64',
        ]);

        try {
            $data = $this->launch->launch(
                $slug,
                (int) $userId,
                $this->currentZoneId($request),
                $request->ip(),
                (string) $request->userAgent(),
                $validated['version'] ?? null,
            );
        } catch (LaunchDeniedException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json(['success' => true, 'data' => $data]);
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
            return response()->json(['success' => false, 'error' => 'Invalid or expired launch token'], 401);
        }

        $appSlug = $validated['app_slug'] ?? null;
        if ($appSlug !== null && $appSlug !== '' && $record->app->slug !== $appSlug) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired launch token'], 401);
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
            return response()->json(['success' => false, 'error' => $guard['error']], $guard['status']);
        }

        $result = $this->launchTokens->verify(
            $validated['launch_token'],
            $validated['app_slug'] ?? null,
        );

        if ($result === null) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired launch token'], 401);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function ping(Request $request, string $slug): JsonResponse
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug'], 422);
        }

        $userId = $request->attributes->get('knf_core_user_id');
        if (empty($userId)) {
            return response()->json(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return response()->json(['success' => false, 'error' => 'App not found'], 404);
        }

        if (!$this->catalog->userCanLaunch($app, (int) $userId, $this->currentZoneId($request))) {
            return response()->json(['success' => false, 'error' => 'You do not have permission to test this app'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->healthcheck->ping($app),
        ]);
    }

    public function usage(Request $request, string $slug): JsonResponse
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug'], 422);
        }

        $userId = $request->attributes->get('knf_core_user_id');
        if (empty($userId)) {
            return response()->json(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $validated = $request->validate([
            'action' => 'required|string|in:' . implode(',', AppUsageService::ALLOWED_ACTIONS),
            'metadata' => 'nullable|array',
        ]);

        try {
            $this->usage->logBySlug(
                (int) $userId,
                $slug,
                $validated['action'],
                $this->currentZoneId($request),
                $validated['metadata'] ?? null,
            );
        } catch (LaunchDeniedException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json(['success' => true, 'data' => ['logged' => true]]);
    }

    private function currentZoneId(Request $request): ?int
    {
        $zoneId = $request->attributes->get('knf_core_user_zone_id_current');

        return $zoneId !== null && $zoneId !== '' ? (int) $zoneId : null;
    }
}
