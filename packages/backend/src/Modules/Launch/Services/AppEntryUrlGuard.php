<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;

final class AppEntryUrlGuard
{
    /**
     * @throws LaunchDeniedException
     */
    public function assertLaunchable(App $app): void
    {
        if ($app->runtime_type === AppRuntimeType::HOSTED) {
            return;
        }

        $url = trim((string) ($app->entry_url ?? ''));
        if ($url === '') {
            throw new LaunchDeniedException('App entry URL is not configured', 422);
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new LaunchDeniedException('App entry URL is invalid', 422);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw new LaunchDeniedException('App entry URL is invalid', 422);
        }

        if (in_array($scheme, ['javascript', 'data', 'blob', 'file'], true)) {
            throw new LaunchDeniedException('App entry URL uses a blocked scheme', 422);
        }

        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_starts_with($host, '127.');

        if ($scheme === 'http:' && $isLocal && AppManifestApiUrl::allowsLocalhostApiUrls()) {
            return;
        }

        if ($scheme !== 'https:') {
            throw new LaunchDeniedException('App entry URL must use HTTPS', 422);
        }

        $origin = $this->originOf($url);
        if ($origin === null) {
            throw new LaunchDeniedException('App entry URL is invalid', 422);
        }

        $allowed = $this->allowedOrigins();
        if ($allowed === []) {
            if (AppManifestApiUrl::allowsLocalhostApiUrls()) {
                return;
            }

            throw new LaunchDeniedException(
                'App entry URL is not allowed — configure APPHUB_ALLOWED_RUNTIME_ORIGINS',
                403,
            );
        }

        if (!in_array($origin, $allowed, true)) {
            throw new LaunchDeniedException('App entry URL origin is not in the host allowlist', 403);
        }
    }

    /** @return list<string> */
    private function allowedOrigins(): array
    {
        $raw = config('apphub.allowed_runtime_origins', []);
        if (!is_array($raw)) {
            return [];
        }

        $origins = [];
        foreach ($raw as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $origin = $this->originOf($entry) ?? rtrim($entry, '/');
            if (!in_array($origin, $origins, true)) {
                $origins[] = $origin;
            }
        }

        return $origins;
    }

    private function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $origin = $scheme . '://' . $host;
        if ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $origin .= ':' . $port;
        }

        return $origin;
    }
}
