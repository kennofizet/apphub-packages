<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentIntentService;
use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;
use Kennofizet\AppHub\Modules\Launch\Services\UserNotificationService;

class BridgeConsentController extends Controller
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppBridgeConsentService $consents,
        private readonly AppBridgeConsentIntentService $intents,
        private readonly UserNotificationService $notifications,
    ) {
    }

    /** Mint short-lived token when install permission dialog opens (Hub UI only). */
    public function createIntent(Request $request, string $slug): JsonResponse
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

        $userId = (int) self::currentUserId();
        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        if (!$this->catalog->userCanLaunch($app, $userId, self::currentUserZoneIdList())) {
            return $this->apiErrorResponse('You do not have permission for this app', 403);
        }

        $bundleVersion = $this->normalizeBundleVersion($validated['version'] ?? null);

        return $this->apiResponseWithContext(
            $this->intents->createIntent($app, $userId, $bundleVersion),
        );
    }

    /** Record install consent — requires valid install intent from permission dialog. */
    public function store(Request $request, string $slug): JsonResponse
    {
        if ($response = $this->ensureValidSlug($slug)) {
            return $response;
        }

        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'version' => 'nullable|string|max:64',
            'intent_token' => 'required|string|min:32|max:128',
        ]);

        $userId = (int) self::currentUserId();
        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return $this->apiErrorResponse('App not found', 404);
        }

        if (!$this->catalog->userCanLaunch($app, $userId, self::currentUserZoneIdList())) {
            return $this->apiErrorResponse('You do not have permission for this app', 403);
        }

        if (!$this->intents->consumeIntent($validated['intent_token'], $app, $userId)) {
            return $this->apiErrorResponse(
                'Invalid or expired install intent — open the permission dialog again',
                403,
            );
        }

        $bundleVersion = $this->normalizeBundleVersion($validated['version'] ?? null);
        $recorded = $this->consents->recordManifestConsents($app, $userId, $bundleVersion);

        return $this->apiResponseWithContext(['scopes_recorded' => $recorded]);
    }

    /** Revoke install consent and clear inbox rows when user uninstalls from Hub. */
    public function destroy(string $slug): JsonResponse
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

        $revoked = $this->consents->revokeAllForUser($app, $userId);
        $dismissed = $this->notifications->dismissAllForUserAndApp($userId, (int) $app->id);

        return $this->apiResponseWithContext([
            'scopes_revoked' => $revoked,
            'notifications_dismissed' => $dismissed,
            'unread_count' => $this->notifications->unreadCountForUser($userId),
        ]);
    }

    private function normalizeBundleVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $normalized = AppSemver::normalize($version);

        return $normalized !== '' && AppSemver::isValid($normalized) ? $normalized : null;
    }
}
