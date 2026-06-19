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
        self::assertRegisterableUrl($url);
    }

    /**
     * Format + optional enterprise host policy. Catalog entry_url + DEV approval is the per-app allowlist.
     *
     * @throws LaunchDeniedException
     */
    public static function assertRegisterableUrl(string $url): void
    {
        self::assertUrlFormat($url);
        self::assertEnterpriseAllowlistIfConfigured($url);
    }

    /**
     * @throws LaunchDeniedException
     */
    private static function assertUrlFormat(string $url): void
    {
        $url = trim($url);
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

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new LaunchDeniedException('App entry URL must use HTTP or HTTPS', 422);
        }

        if (self::originOfStatic($url) === null) {
            throw new LaunchDeniedException('App entry URL is invalid', 422);
        }

        $isLocal = self::isLoopbackHost($host);

        if ($scheme === 'http' && $isLocal && self::allowsLocalhostEntryUrls()) {
            return;
        }

        if ($scheme !== 'https') {
            throw new LaunchDeniedException('App entry URL must use HTTPS', 422);
        }
    }

    /**
     * Enterprise cap when configured; otherwise production requires explicit catalog-trust opt-in.
     *
     * @throws LaunchDeniedException
     */
    private static function assertEnterpriseAllowlistIfConfigured(string $url): void
    {
        $allowed = self::enterpriseOriginsFromConfig();
        if ($allowed !== []) {
            $origin = self::originOfStatic($url);
            if ($origin === null || !in_array($origin, $allowed, true)) {
                throw new LaunchDeniedException(
                    'App entry URL origin is not in the host enterprise allowlist (APPHUB_ALLOWED_RUNTIME_ORIGINS)',
                    403,
                );
            }

            return;
        }

        if (self::allowsAnyPublisherRuntimeOrigin()) {
            return;
        }

        throw new LaunchDeniedException(
            'App entry URL origin is not allowed. Set APPHUB_ALLOWED_RUNTIME_ORIGINS or APPHUB_ALLOW_ANY_PUBLISHER_RUNTIME_ORIGIN=true for catalog entry_url + DEV approval.',
            403,
        );
    }

    private static function allowsAnyPublisherRuntimeOrigin(): bool
    {
        if (!function_exists('config')) {
            return false;
        }

        return (bool) config('apphub.allow_any_publisher_runtime_origin', false);
    }

    private static function isLoopbackHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_starts_with($host, '127.');
    }

    private static function allowsLocalhostEntryUrls(): bool
    {
        if (AppManifestApiUrl::allowsLocalhostApiUrls()) {
            return true;
        }

        if (!function_exists('config')) {
            return false;
        }

        $env = strtolower(trim((string) config('app.env', '')));

        return in_array($env, ['local', 'testing'], true);
    }

    /** @return list<string> */
    private static function enterpriseOriginsFromConfig(): array
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
            $origin = self::originOfStatic($entry) ?? rtrim($entry, '/');
            if (!in_array($origin, $origins, true)) {
                $origins[] = $origin;
            }
        }

        return $origins;
    }

    private static function originOfStatic(string $url): ?string
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
