<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Http\Request;

/**
 * Public URLs derived from APP_URL + packages-core api prefix + apphub api prefix.
 * Hub SPA origin comes from the browser Origin/Referer on bootstrap (frontend host).
 */
final class AppHubPublicUrlService
{
    /** Backend APP_URL — fallback when bootstrap has no Origin header. */
    public function hubPublicUrlFromConfig(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Where the Hub Vue app runs — from browser Origin on bootstrap, not Laravel APP_URL.
     */
    public function resolveHubPublicUrl(Request $request): string
    {
        $origin = trim((string) $request->headers->get('Origin', ''));
        if ($origin !== '' && $this->isHttpOrigin($origin)) {
            return rtrim($origin, '/');
        }

        $referer = trim((string) $request->headers->get('Referer', ''));
        if ($referer !== '') {
            $fromReferer = $this->originFromUrl($referer);
            if ($fromReferer !== '') {
                return $fromReferer;
            }
        }

        return $this->hubPublicUrlFromConfig();
    }

    /** API base for catalog/launch/runtime — always backend APP_URL + prefixes. */
    public function apiBaseUrl(): string
    {
        $prefix = trim((string) config('packages-core.api_prefix', 'api/knf'), '/');
        $hub = trim((string) config('apphub.api_prefix', 'apphub'), '/');

        return $this->hubPublicUrlFromConfig() . '/' . $prefix . '/' . $hub;
    }

    private function isHttpOrigin(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    private function originFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }
}
