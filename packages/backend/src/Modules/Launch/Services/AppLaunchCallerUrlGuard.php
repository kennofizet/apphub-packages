<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Http\Request;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use Kennofizet\PackagesCore\Services\TokenService;

final class AppLaunchCallerUrlGuard
{
    public function __construct(
        private readonly AppVersionService $versions,
        private readonly TokenService $tokenService,
    ) {
    }

    /**
     * @param array<string, mixed> $launchPayload
     * @return array{ok: true}|array{ok: false, error: string, status: int}
     */
    public function validate(App $app, ?string $bundleVersion, Request $request, array $launchPayload = []): array
    {
        if ($this->isHubInternalRequest($request, $launchPayload)) {
            return ['ok' => true];
        }

        $allowed = $this->versions->apiUrlsForLaunchBundle($app, $bundleVersion);
        if ($allowed === []) {
            return [
                'ok' => false,
                'error' => 'App manifest api_urls is required — republish with declared backend URLs',
                'status' => 403,
            ];
        }

        $callerUrl = $this->resolveCallerUrl($request);
        if ($callerUrl === null || $callerUrl === '') {
            return [
                'ok' => false,
                'error' => 'Caller URL required — send X-AppHub-Caller-Url or caller_url matching manifest api_urls',
                'status' => 403,
            ];
        }

        if (!AppManifestApiUrl::matchesAllowed($callerUrl, $allowed)) {
            return [
                'ok' => false,
                'error' => 'Caller URL is not declared in app manifest api_urls',
                'status' => 403,
            ];
        }

        return ['ok' => true];
    }

    private function resolveCallerUrl(Request $request): ?string
    {
        $header = trim((string) $request->header('X-AppHub-Caller-Url', ''));
        if ($header !== '') {
            return $header;
        }

        $body = $request->input('caller_url');
        if (is_string($body) && trim($body) !== '') {
            return trim($body);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $launchPayload
     */
    private function isHubInternalRequest(Request $request, array $launchPayload): bool
    {
        if (strtolower(trim((string) $request->header('X-AppHub-Internal', ''))) !== '1') {
            return false;
        }

        $knfToken = (string) $request->header('X-Knf-Token', '');
        if ($knfToken === '') {
            return false;
        }

        $sessionUserId = $this->tokenService->validateToken($knfToken);
        $launchUserId = (int) ($launchPayload['user_id'] ?? 0);
        if ($sessionUserId === null || $launchUserId < 1 || (int) $sessionUserId !== $launchUserId) {
            return false;
        }

        return true;
    }
}
