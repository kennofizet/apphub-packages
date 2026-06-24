<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;
use Kennofizet\AppHub\Modules\Launch\Services\UserNotificationService;
use Kennofizet\PackagesCore\Models\User;
use RuntimeException;

class BridgeController extends Controller
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly LaunchTokenService $launchTokens,
        private readonly UserNotificationService $notifications,
    ) {
    }

    public function user(Request $request): JsonResponse
    {
        $launch = $request->attributes->get('apphub_launch', []);
        $app = $this->catalog->findBySlug((string) ($launch['app_slug'] ?? ''));
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        if (!$this->launchTokens->hasUserReadAccess($launch)) {
            return $this->apiErrorResponse('Scope not granted', 403);
        }

        $data = $this->resolveBridgeUser($launch);

        if ($this->launchTokens->hasScope($launch, 'user.profile')) {
            $data['locale'] = 'vi';
            $data['avatar'] = null;
        }

        return $this->apiResponseWithContext($data);
    }

    /** Publisher backend only — caller IP must match manifest api_urls. */
    public function notify(Request $request): JsonResponse
    {
        $launch = $request->attributes->get('apphub_launch', []);
        $app = $this->catalog->findBySlug((string) ($launch['app_slug'] ?? ''));
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        if (!$this->launchTokens->hasScope($launch, AppBridgeScope::DESKTOP_NOTIFY)) {
            return $this->apiErrorResponse('Scope not granted', 403);
        }

        $permissions = AppBridgeScope::fromManifest(is_array($app->manifest) ? $app->manifest : null);
        if (!in_array(AppBridgeScope::DESKTOP_NOTIFY, $permissions, true)) {
            return $this->apiErrorResponse('App manifest does not declare desktop.notify', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:2000',
            'broadcast' => 'sometimes|boolean',
            'user_ids' => 'sometimes|array|max:50',
            'user_ids.*' => 'integer|min:1',
        ]);

        try {
            $result = $this->notifications->fanOutFromPublisher(
                $app,
                $validated['title'],
                (string) ($validated['body'] ?? ''),
                (int) ($launch['user_id'] ?? 0),
                (bool) ($validated['broadcast'] ?? false),
                array_values($validated['user_ids'] ?? []),
            );
        } catch (RuntimeException $e) {
            return $this->apiErrorResponse($e->getMessage(), 422);
        }

        return $this->apiResponseWithContext([
            'accepted' => true,
            'app_slug' => $app->slug,
            'recipients' => $result['created'],
        ]);
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
