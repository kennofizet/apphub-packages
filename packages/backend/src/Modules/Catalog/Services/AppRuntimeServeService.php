<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Http\Request;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
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
    ) {
    }

    public function buildRuntimeIndexUrl(App $app, string $baseUrl, ?string $entry = null): string
    {
        $entry = ltrim($entry ?? (string) ($app->bundle_entry ?: 'index.html'), '/');

        return rtrim($baseUrl, '/')
            . '/apps/' . rawurlencode($app->slug)
            . '/runtime/' . implode('/', array_map('rawurlencode', explode('/', $entry)));
    }

    public function serve(App $app, string $path, Request $request): Response
    {
        if ($app->runtime_type !== AppRuntimeType::HOSTED) {
            return new Response('Not a hosted app', 404);
        }

        $token = $this->authorize($app, $request);
        if ($token === null) {
            return new Response('Invalid or expired launch token', 401);
        }

        $bundle = $this->versions->resolveBundle($app, $token->bundle_version);
        if ($bundle === null) {
            return new Response('Bundle not published', 404);
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            $path = $bundle['entry'];
        }

        if (str_contains($path, '..')) {
            return new Response('Invalid path', 400);
        }

        try {
            $absolute = $this->bundles->absolutePath($bundle['path'], $path);
        } catch (RuntimeException) {
            return new Response('Invalid path', 400);
        }

        if (!is_file($absolute)) {
            return new Response('Not found', 404);
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($absolute));
        $response->headers->set('Content-Type', $this->mimeType($absolute));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; frame-ancestors *");

        if ($this->isHtml($absolute)) {
            $cookieName = $this->runtimeCookieName($app->slug);
            $token = (string) $request->query('launch_token', '');
            if ($token !== '') {
                $response->headers->setCookie(cookie(
                    $cookieName,
                    hash('sha256', $token),
                    (int) config('apphub.launch_token_ttl', 180) / 60,
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    'lax',
                ));
            }
        }

        return $response;
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
