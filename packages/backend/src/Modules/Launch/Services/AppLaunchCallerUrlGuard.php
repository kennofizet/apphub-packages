<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Http\Request;
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
     * Origin, Referer, and other client headers are not used for authorization.
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
                'error' => 'This app has no manifest api_urls — bridge HTTP APIs (bridge/user, verify-launch-token) are not enabled',
                'status' => 403,
            ];
        }

        $pinnedIps = $this->versions->pinnedIpsForLaunchBundle($app, $bundleVersion);
        $clientIp = trim((string) $request->ip());
        if (!AppManifestApiUrl::clientMatchesAllowedHosts($clientIp, $allowed, $pinnedIps !== [] ? $pinnedIps : null)) {
            return [
                'ok' => false,
                'error' => 'Request source IP is not allowed — caller must run on a host listed in manifest api_urls',
                'status' => 403,
            ];
        }

        return ['ok' => true];
    }
}
