<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;
use Kennofizet\PackagesCore\Models\User;

class BridgeController extends Controller
{
    private const ALLOWED_MESSAGE_TYPES = ['toast', 'banner', 'host-hint'];

    public function __construct(private readonly LaunchTokenService $launchTokens)
    {
    }

    public function user(Request $request): JsonResponse
    {
        $launch = $request->attributes->get('apphub_launch', []);
        if (!$this->launchTokens->hasScope($launch, 'user.read')
            && !$this->launchTokens->hasScope($launch, 'user.profile')) {
            return response()->json(['success' => false, 'error' => 'Scope not granted'], 403);
        }

        $data = $this->resolveBridgeUser($launch);

        if ($this->launchTokens->hasScope($launch, 'user.profile')) {
            $data['locale'] = 'vi';
            $data['avatar'] = null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function desktopMessage(Request $request): JsonResponse
    {
        $launch = $request->attributes->get('apphub_launch', []);
        if (!$this->launchTokens->hasScope($launch, 'desktop.message')) {
            return response()->json(['success' => false, 'error' => 'Scope not granted'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', self::ALLOWED_MESSAGE_TYPES),
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:2000',
            'duration_ms' => 'nullable|integer|min:0|max:600000',
            'priority' => 'nullable|string|in:normal,high',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'accepted' => true,
                'app_slug' => $launch['app_slug'] ?? null,
                'message' => $validated,
            ],
        ]);
    }

    /** Hub shell records user consent for a launch session (host token auth). */
    public function grantScope(Request $request): JsonResponse
    {
        $userId = $request->attributes->get('knf_core_user_id');
        if (empty($userId)) {
            return response()->json(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $validated = $request->validate([
            'launch_token' => 'required|string|min:32|max:128',
            'scope' => 'required|string|in:user.read,user.profile,desktop.notify,desktop.message,desktop.badge',
        ]);

        $payload = $this->launchTokens->peek($validated['launch_token']);
        if ($payload === null) {
            return response()->json(['success' => false, 'error' => 'Invalid launch token'], 404);
        }

        if ((string) ($payload['user_id'] ?? '') !== (string) $userId) {
            return response()->json(['success' => false, 'error' => 'Launch session does not belong to this user'], 403);
        }

        if (!$this->launchTokens->grantScope($validated['launch_token'], $validated['scope'], (string) $userId)) {
            return response()->json(['success' => false, 'error' => 'Could not grant scope'], 500);
        }

        return response()->json(['success' => true, 'data' => ['scope' => $validated['scope']]]);
    }

    /** @param array<string, mixed> $launch */
    private function resolveBridgeUser(array $launch): array
    {
        $userId = (int) ($launch['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['id' => 0, 'name' => 'App Hub User'];
        }

        $user = User::byId($userId)->first();
        if ($user === null) {
            return ['id' => $userId, 'name' => (string) $userId];
        }

        $nameCol = $user->getNameColumn();
        $name = ($nameCol && isset($user->{$nameCol}))
            ? (string) $user->{$nameCol}
            : (string) $user->id;

        return ['id' => $userId, 'name' => $name];
    }
}
