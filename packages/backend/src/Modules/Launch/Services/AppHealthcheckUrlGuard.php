<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;

final class AppHealthcheckUrlGuard
{
    /**
     * @throws LaunchDeniedException
     */
    public static function assertSafeUrl(string $url): void
    {
        $url = trim($url);
        if ($url === '') {
            throw new LaunchDeniedException('healthcheck_url is empty', 422);
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new LaunchDeniedException('healthcheck_url is invalid', 422);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw new LaunchDeniedException('healthcheck_url is invalid', 422);
        }

        if (in_array($scheme, ['javascript', 'data', 'blob', 'file'], true)) {
            throw new LaunchDeniedException('healthcheck_url uses a blocked scheme', 422);
        }

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new LaunchDeniedException('healthcheck_url must use HTTP or HTTPS', 422);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new LaunchDeniedException('healthcheck_url must not include credentials', 422);
        }

        $isLoopback = self::isLoopbackHost($host);

        if ($scheme === 'http' && $isLoopback && self::allowsLocalhostHealthcheck()) {
            self::assertResolvablePublicTarget($host);

            return;
        }

        if ($scheme !== 'https') {
            throw new LaunchDeniedException('healthcheck_url must use HTTPS', 422);
        }

        self::assertResolvablePublicTarget($host);
    }

    /**
     * @throws LaunchDeniedException
     */
    private static function assertResolvablePublicTarget(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::isBlockedIp($host)) {
                throw new LaunchDeniedException('healthcheck_url must not target private or reserved IPs', 422);
            }

            return;
        }

        if (self::isLoopbackHost($host) && !self::allowsLocalhostHealthcheck()) {
            throw new LaunchDeniedException('healthcheck_url must not use localhost in production', 422);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (!is_array($records) || $records === []) {
            $resolved = @gethostbyname($host);
            if ($resolved === $host || $resolved === '') {
                throw new LaunchDeniedException('healthcheck_url host could not be resolved', 422);
            }
            if (self::isBlockedIp($resolved)) {
                throw new LaunchDeniedException('healthcheck_url must not target private or reserved IPs', 422);
            }

            return;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!is_string($ip) || $ip === '') {
                continue;
            }
            if (self::isBlockedIp($ip)) {
                throw new LaunchDeniedException('healthcheck_url must not target private or reserved IPs', 422);
            }
        }
    }

    private static function isBlockedIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (AppManifestApiUrl::isLoopbackHost($ip)) {
            return !self::allowsLocalhostHealthcheck();
        }

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }

    private static function isLoopbackHost(string $host): bool
    {
        return AppManifestApiUrl::isLoopbackHost($host);
    }

    private static function allowsLocalhostHealthcheck(): bool
    {
        if (!function_exists('config')) {
            return true;
        }

        try {
            if (AppManifestApiUrl::allowsLocalhostApiUrls()) {
                return true;
            }

            $env = strtolower(trim((string) config('app.env', '')));

            return in_array($env, ['local', 'testing'], true);
        } catch (\Throwable) {
            return true;
        }
    }
}
