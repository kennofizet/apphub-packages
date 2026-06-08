<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;

class LaunchController extends Controller
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(private readonly LaunchTokenService $launchTokens)
    {
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
}
