<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Http\Request;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use Kennofizet\AppHub\Modules\Launch\Models\AppLaunchToken;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class AppRuntimeServeService
{
    public function __construct(
        private readonly AppBundleStorageService $bundles,
        private readonly LaunchTokenService $launchTokens,
        private readonly AppVersionService $versions,
        private readonly AppHubPublicUrlService $publicUrls,
    ) {
    }

    public function runtimeApiBaseUrl(): string
    {
        return $this->publicUrls->apiBaseUrl();
    }

    public function buildRuntimeIndexUrl(App $app, ?string $baseUrl = null, ?string $entry = null): string
    {
        $entry = ltrim($entry ?? (string) ($app->bundle_entry ?: 'index.html'), '/');
        $base = rtrim($baseUrl ?? $this->runtimeApiBaseUrl(), '/');

        return $base
            . '/apps/' . rawurlencode($app->slug)
            . '/runtime/' . implode('/', array_map('rawurlencode', explode('/', $entry)));
    }

    public function serve(App $app, string $path, Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->corsPreflight($request);
        }

        if ($app->runtime_type !== AppRuntimeType::HOSTED) {
            return $this->runtimeError('Not a hosted app', 404, $request);
        }

        $token = $this->authorize($app, $request);
        if ($token === null) {
            return $this->runtimeError('Invalid or expired launch token', 401, $request);
        }

        $bundle = $this->versions->resolveLaunchBundle($app, $token->bundle_version, (int) $token->user_id);
        if ($bundle === null) {
            return $this->runtimeError('Bundle not published', 404, $request);
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            $path = $bundle['entry'];
        }

        if (str_contains($path, '..')) {
            return $this->runtimeError('Invalid path', 400, $request);
        }

        try {
            $absolute = $this->bundles->absolutePath($bundle['path'], $path);
        } catch (RuntimeException) {
            return $this->runtimeError('Invalid path', 400, $request);
        }

        if (!is_file($absolute)) {
            return $this->runtimeError('Not found', 404, $request);
        }

        $plainToken = (string) $request->query('launch_token', '');
        $connectOrigins = $this->resolveRuntimeConnectOrigins($app, $token, $bundle);

        if ($this->isHtml($absolute)) {
            $content = (string) file_get_contents($absolute);
            if ($plainToken !== '') {
                $content = $this->rewriteHtmlLaunchToken($content, $plainToken);
            }
            $content = $this->injectRuntimeScrollbarStyles($content);
            $content = $this->injectHostedStorageShim($content);

            $response = new Response($content);
            $response->headers->set('Content-Type', $this->mimeType($absolute));
            $this->applyRuntimeSecurityHeaders($response, $connectOrigins, $request);

            if ($plainToken !== '') {
                $response->headers->setCookie($this->runtimeAuthCookie($app->slug, $plainToken, $request));
            }
        } else {
            $response = new BinaryFileResponse($absolute);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($absolute));
            $response->headers->set('Content-Type', $this->mimeType($absolute));
            $this->applyRuntimeSecurityHeaders($response, $connectOrigins, $request);
        }

        $this->applyRuntimeCors($response, $request);

        return $response;
    }

    private function corsPreflight(Request $request): Response
    {
        $response = new Response('', 204);
        $this->applyRuntimeCors($response, $request);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    private function runtimeError(string $message, int $status, ?Request $request = null): Response
    {
        $response = new Response($message, $status);
        $this->applyRuntimeCors($response, $request);

        return $response;
    }

    /**
     * Hosted apps load in a sandboxed iframe (opaque origin). Vite bundles use crossorigin on assets,
     * so subresources require CORS. Auth uses launch_token query param (cookie is unreliable cross-site).
     * Reflects allowed Hub/product origins when present; falls back to * for opaque sandbox.
     */
    private function applyRuntimeCors(Response $response, ?Request $request = null): void
    {
        $origin = $request !== null ? trim((string) $request->headers->get('Origin', '')) : '';
        if ($origin !== '' && $origin !== 'null' && $this->isAllowedRuntimeCorsOrigin($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');

            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', '*');
    }

    private function isAllowedRuntimeCorsOrigin(string $origin): bool
    {
        foreach (['allowed_hub_origins', 'allowed_product_origins'] as $key) {
            $allowed = config('apphub.' . $key, []);
            if (!is_array($allowed)) {
                continue;
            }
            foreach ($allowed as $entry) {
                if (is_string($entry) && rtrim(trim($entry), '/') === rtrim($origin, '/')) {
                    return true;
                }
            }
        }

        return AppManifestApiUrl::allowsLocalhostApiUrls() && $this->isLoopbackOrigin($origin);
    }

    /**
     * @param list<string> $connectOrigins
     */
    private function applyRuntimeSecurityHeaders(Response $response, array $connectOrigins, Request $request): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $connectSrc = $connectOrigins !== []
            ? "connect-src 'self' " . implode(' ', $connectOrigins)
            : "connect-src 'self'";
        $frameAncestors = $this->frameAncestorsDirective($request);
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; font-src 'self' data:; {$connectSrc}; frame-ancestors {$frameAncestors}",
        );
    }

    private function frameAncestorsDirective(Request $request): string
    {
        $list = ["'self'"];

        foreach (config('apphub.allowed_hub_origins', []) as $origin) {
            $this->appendFrameAncestor($list, is_string($origin) ? $origin : '');
        }

        foreach (config('apphub.allowed_product_origins', []) as $origin) {
            $this->appendFrameAncestor($list, is_string($origin) ? $origin : '');
        }

        $refererOrigin = $this->refererOrigin($request);
        if ($refererOrigin !== null && $this->mayFrameFromOrigin($refererOrigin)) {
            $this->appendFrameAncestor($list, $refererOrigin);
        }

        foreach (['hub_origin', 'product_origin'] as $param) {
            $origin = trim((string) $request->query($param, ''));
            if ($origin !== '' && $this->mayFrameFromOrigin($origin)) {
                $this->appendFrameAncestor($list, $origin);
            }
        }

        return implode(' ', $list);
    }

    private function mayFrameFromOrigin(string $origin): bool
    {
        foreach (['allowed_hub_origins', 'allowed_product_origins'] as $key) {
            $allowed = config('apphub.' . $key, []);
            if (!is_array($allowed)) {
                continue;
            }
            foreach ($allowed as $entry) {
                if (is_string($entry) && rtrim(trim($entry), '/') === rtrim($origin, '/')) {
                    return true;
                }
            }
        }

        return AppManifestApiUrl::allowsLocalhostApiUrls() && $this->isLoopbackOrigin($origin);
    }

    private function isLoopbackOrigin(string $origin): bool
    {
        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return str_starts_with($host, '127.');
    }

    private function refererOrigin(Request $request): ?string
    {
        $referer = trim((string) $request->headers->get('Referer', ''));
        if ($referer === '') {
            return null;
        }

        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $origin = $scheme . '://' . $host;
        if ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    /** @param list<string> $list */
    private function appendFrameAncestor(array &$list, string $origin): void
    {
        $origin = rtrim(trim($origin), '/');
        if ($origin === '' || in_array($origin, $list, true)) {
            return;
        }

        $list[] = $origin;
    }
    /**
     * @param array{path: string, entry: string} $bundle
     * @return list<string>
     */
    private function resolveRuntimeConnectOrigins(App $app, AppLaunchToken $token, array $bundle): array
    {
        $bundleVersion = $token->bundle_version !== null ? trim((string) $token->bundle_version) : null;
        if ($bundleVersion === '') {
            $bundleVersion = null;
        }

        $apiUrls = $this->versions->apiUrlsForLaunchBundle($app, $bundleVersion);
        if ($apiUrls === []) {
            try {
                $manifestPath = $this->bundles->absolutePath($bundle['path'], 'manifest.json');
                if (is_file($manifestPath)) {
                    $decoded = json_decode((string) file_get_contents($manifestPath), true);
                    $apiUrls = AppManifestApiUrl::fromManifest(is_array($decoded) ? $decoded : null);
                }
            } catch (RuntimeException) {
                // ignore unreadable bundle manifest
            }
        }

        return AppManifestApiUrl::connectSrcOrigins($apiUrls);
    }

    /** @return \Symfony\Component\HttpFoundation\Cookie */
    private function runtimeAuthCookie(string $slug, string $plainToken, Request $request): \Symfony\Component\HttpFoundation\Cookie
    {
        $ttlMinutes = max(1, (int) config('apphub.launch_token_ttl', 180) / 60);

        return cookie(
            $this->runtimeCookieName($slug),
            hash('sha256', $plainToken),
            $ttlMinutes,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            $request->isSecure() ? 'none' : 'lax',
        );
    }

    private function rewriteHtmlLaunchToken(string $html, string $launchToken): string
    {
        $appendToken = static function (string $url) use ($launchToken): string {
            if (
                $url === ''
                || str_starts_with($url, 'data:')
                || str_starts_with($url, 'blob:')
                || str_starts_with($url, 'javascript:')
            ) {
                return $url;
            }

            $separator = str_contains($url, '?') ? '&' : '?';

            return $url . $separator . 'launch_token=' . rawurlencode($launchToken);
        };

        $rewritten = preg_replace_callback(
            '/\b(src|href)\s*=\s*(["\'])([^"\']+)\2/i',
            static function (array $matches) use ($appendToken): string {
                $attr = strtolower($matches[1]);
                if ($attr !== 'src' && $attr !== 'href') {
                    return $matches[0];
                }

                return $matches[1] . '=' . $matches[2] . $appendToken($matches[3]) . $matches[2];
            },
            $html,
        );

        return is_string($rewritten) ? $rewritten : $html;
    }

    private function injectRuntimeScrollbarStyles(string $html): string
    {
        $css = $this->runtimeDocumentScrollbarCss();
        if ($css === '') {
            return $html;
        }

        $styleTag = '<style id="apphub-runtime-scrollbars">' . $css . '</style>';

        return $this->injectAfterHeadOpen($html, $styleTag);
    }

    /**
     * Hosted zip apps run in an opaque-origin sandbox — native localStorage is blocked.
     * Inject Hub storage shim before any publisher scripts.
     */
    private function injectHostedStorageShim(string $html): string
    {
        $js = $this->hostedStorageShimSource();
        if ($js === '') {
            return $html;
        }

        $scriptTag = '<script id="apphub-hosted-storage-shim">' . $js . '</script>';

        return $this->injectAfterHeadOpen($html, $scriptTag);
    }

    private function injectAfterHeadOpen(string $html, string $fragment): string
    {
        if (preg_match('/<head\b[^>]*>/i', $html) === 1) {
            $injected = preg_replace('/<head\b[^>]*>/i', '$0' . $fragment, $html, 1);

            return is_string($injected) ? $injected : $html;
        }

        if (preg_match('/<html\b[^>]*>/i', $html) === 1) {
            $injected = preg_replace('/<html\b[^>]*>/i', '$0' . $fragment, $html, 1);

            return is_string($injected) ? $injected : $html;
        }

        return $fragment . $html;
    }

    private function hostedStorageShimSource(): string
    {
        return $this->readCatalogResource('hosted-storage-shim.js');
    }

    private function runtimeDocumentScrollbarCss(): string
    {
        return $this->readCatalogResource('runtime-document-scrollbars.css');
    }

    private function readCatalogResource(string $filename): string
    {
        static $cache = [];
        if (array_key_exists($filename, $cache)) {
            return $cache[$filename];
        }

        $path = dirname(__DIR__) . '/Resources/' . $filename;
        if (!is_readable($path)) {
            $cache[$filename] = '';

            return $cache[$filename];
        }

        $cache[$filename] = trim((string) file_get_contents($path));

        return $cache[$filename];
    }

    private function authorize(App $app, Request $request): ?AppLaunchToken
    {
        $token = (string) $request->query('launch_token', '');
        if ($token !== '') {
            $record = $this->launchTokens->findValidForRuntimeByPlainToken($token, $app->slug);
            if ($record !== null) {
                return $record;
            }
        }

        $cookieHash = (string) $request->cookie($this->runtimeCookieName($app->slug), '');

        return $this->launchTokens->findValidForRuntimeByHash($cookieHash, $app->slug);
    }

    private function runtimeCookieName(string $slug): string
    {
        return 'apphub_rt_' . preg_replace('/[^a-z0-9_-]/', '', $slug);
    }

    private function isHtml(string $path): bool
    {
        return str_ends_with(strtolower($path), '.html') || str_ends_with(strtolower($path), '.htm');
    }

    private function mimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'html', 'htm' => 'text/html; charset=UTF-8',
            'js', 'mjs' => 'application/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'map' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
