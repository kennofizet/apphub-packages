<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentIntentService;
use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;

class BridgeConsentController extends Controller
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppBridgeConsentService $consents,
        private readonly AppBridgeConsentIntentService $intents,
    ) {
    }

    /** Mint short-lived token when install permission dialog opens (Hub UI only). */
    public function createIntent(Request $request, string $slug): JsonResponse
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

        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return response()->json(['success' => false, 'error' => 'App not found'], 404);
        }

        if (!$this->catalog->userCanLaunch($app, (int) $userId, $this->currentZoneId($request))) {
            return response()->json(['success' => false, 'error' => 'You do not have permission for this app'], 403);
        }

        $bundleVersion = $this->normalizeBundleVersion($validated['version'] ?? null);

        return response()->json([
            'success' => true,
            'data' => $this->intents->createIntent($app, (int) $userId, $bundleVersion),
        ]);
    }

    /** Record install consent — requires valid install intent from permission dialog. */
    public function store(Request $request, string $slug): JsonResponse
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
            'intent_token' => 'required|string|min:32|max:128',
        ]);

        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return response()->json(['success' => false, 'error' => 'App not found'], 404);
        }

        if (!$this->catalog->userCanLaunch($app, (int) $userId, $this->currentZoneId($request))) {
            return response()->json(['success' => false, 'error' => 'You do not have permission for this app'], 403);
        }

        if (!$this->intents->consumeIntent($validated['intent_token'], $app, (int) $userId)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired install intent — open the permission dialog again',
            ], 403);
        }

        $bundleVersion = $this->normalizeBundleVersion($validated['version'] ?? null);
        $recorded = $this->consents->recordManifestConsents($app, (int) $userId, $bundleVersion);

        return response()->json([
            'success' => true,
            'data' => ['scopes_recorded' => $recorded],
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

    private function currentZoneId(Request $request): ?int
    {
        $zoneId = $request->attributes->get('knf_core_user_zone_id_current');

        return $zoneId !== null && $zoneId !== '' ? (int) $zoneId : null;
    }
}
