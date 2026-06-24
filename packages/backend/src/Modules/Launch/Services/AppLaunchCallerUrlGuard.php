<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;

final class AppLaunchCallerUrlGuard
{
    public function __construct(
        private readonly AppVersionService $versions,
    ) {
    }

    /**
     * Caller TCP IP must resolve to a host declared in manifest api_urls (DNS A/AAAA).
     * Optional loopback hardening when APPHUB_BRIDGE_PROXY_SECRET is set.
     *
     * @param array<string, mixed> $launchPayload
     * @return array{ok: true}|array{ok: false, error: string, status: int}
     */
    public function validate(App $app, ?string $bundleVersion, Request $request, array $launchPayload = []): array
    {
        unset($launchPayload);

        $allowed = $this->versions->apiUrlsForLaunchBundle($app, $bundleVersion);
        if ($allowed === []) {
            return [
                'ok' => false,
                'error' => 'This app has no manifest api_urls — publisher bridge HTTP (bridge/user, bridge/notify) is not enabled',
                'status' => 403,
            ];
        }

        $pinnedIps = $this->versions->pinnedIpsForLaunchBundle($app, $bundleVersion);
        if (AppManifestApiUrl::requiresApiUrlIpPins() && $pinnedIps === []) {
            return [
                'ok' => false,
                'error' => 'api_urls IP pins are required but missing — re-publish the app bundle',
                'status' => 403,
            ];
        }

        $clientIp = trim((string) $request->ip());
        if (!AppManifestApiUrl::clientMatchesAllowedHosts($clientIp, $allowed, $pinnedIps !== [] ? $pinnedIps : null)) {
            return [
                'ok' => false,
                'error' => 'Request source IP is not allowed — caller must run on a host listed in manifest api_urls',
                'status' => 403,
            ];
        }

        if (AppManifestApiUrl::allowedUrlsAreLoopbackOnly($allowed)) {
            if (!AppManifestApiUrl::allowsLocalhostApiUrls()) {
                return [
                    'ok' => false,
                    'error' => 'Loopback api_urls are not allowed in this environment',
                    'status' => 403,
                ];
            }

            $proxySecret = AppManifestApiUrl::bridgeProxySecretFromConfig();
            if ($proxySecret === '' && AppManifestApiUrl::requiresBridgeProxySecretOnLoopback()) {
                return [
                    'ok' => false,
                    'error' => 'Loopback api_urls require APPHUB_BRIDGE_PROXY_SECRET in this environment',
                    'status' => 403,
                ];
            }

            if ($proxySecret === '') {
                Log::warning('AppHub bridge: loopback api_urls without APPHUB_BRIDGE_PROXY_SECRET — any local process may call bridge endpoints', [
                    'app_slug' => $app->slug,
                    'client_ip' => $clientIp,
                ]);
            }

            $attestation = AppManifestApiUrl::validateLoopbackBridgeProxyAttestation($request, $allowed, $proxySecret);
            if ($attestation['ok'] !== true) {
                return $attestation;
            }
        }

        return ['ok' => true];
    }
}
