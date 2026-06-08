<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Support\IntegrationDocs;
use Kennofizet\AppHub\Support\LaunchTokenService;

class AppHubController extends Controller
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(private readonly LaunchTokenService $launchTokens)
    {
    }

    public function bootstrap(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
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

    public function launch(Request $request, string $slug): JsonResponse
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug'], 422);
        }

        $userId = $request->attributes->get('knf_core_user_id');
        if (empty($userId)) {
            return response()->json(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $launch = $this->launchTokens->mint($slug, (string) $userId);

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $slug,
                'runtime_url' => null,
                'launch_token' => $launch['launch_token'],
                'session_id' => $launch['session_id'],
                'scopes_granted' => $launch['scopes_granted'],
                'message' => 'Backend stub — use App Store mock catalog in frontend.',
            ],
        ]);
    }

    public function integrationDocs(): JsonResponse
    {
        try {
            $doc = IntegrationDocs::read();

            return response()->json(IntegrationDocs::forPublisher($doc));
        } catch (\JsonException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 500);
        } catch (\RuntimeException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 404);
        }
    }

    /** Full contract for host integrators only — same auth, not for publisher apps. */
    public function integrationDocsInternal(): JsonResponse
    {
        try {
            return response()->json(IntegrationDocs::read());
        } catch (\JsonException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 500);
        } catch (\RuntimeException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 404);
        }
    }
}
