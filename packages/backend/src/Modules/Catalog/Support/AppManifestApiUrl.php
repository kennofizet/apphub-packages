<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

final class AppManifestApiUrl
{
    private const MAX_URLS = 20;

    private const MAX_LENGTH = 2048;

    /**
     * @param array<string, mixed>|null $manifest
     * @return list<string>
     */
    public static function fromManifest(?array $manifest): array
    {
        if ($manifest === null) {
            return [];
        }

        $urls = self::normalizeList($manifest['api_urls'] ?? null);
        $legacy = self::normalizeSingle($manifest['api_base_url'] ?? null);
        if ($legacy !== null && !in_array($legacy, $urls, true)) {
            array_unshift($urls, $legacy);
        }

        return $urls;
    }

    /**
     * @return list<string>
     */
    public static function normalizeList(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (!is_array($raw)) {
            return [];
        }

        $urls = [];
        foreach ($raw as $item) {
            $normalized = null;
            if (is_string($item)) {
                $normalized = self::normalizeSingle($item);
            } elseif (is_array($item) && isset($item['url']) && is_string($item['url'])) {
                $normalized = self::normalizeSingle($item['url']);
            }

            if ($normalized === null || in_array($normalized, $urls, true)) {
                continue;
            }

            $urls[] = $normalized;
            if (count($urls) >= self::MAX_URLS) {
                break;
            }
        }

        return $urls;
    }

    public static function normalizeSingle(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $value = trim($raw);
        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $path = (string) ($parts['path'] ?? '');
        if ($path !== '/' && $path !== '') {
            $path = rtrim($path, '/');
        } else {
            $path = '';
        }

        $origin = $scheme . '://' . $host;
        if ($port !== null && !self::isDefaultPort($scheme, $port)) {
            $origin .= ':' . $port;
        }

        return $path !== '' ? $origin . $path : $origin;
    }

    /**
     * @param list<string> $allowed
     */
    public static function matchesAllowed(string $candidate, array $allowed): bool
    {
        $normalized = self::normalizeSingle($candidate);
        if ($normalized === null) {
            return false;
        }

        foreach ($allowed as $entry) {
            if (self::matchesEntry($normalized, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Server client IP must resolve to a host in manifest api_urls (DNS). Origin/Referer are not trusted.
     *
     * @param list<string> $allowed
     */
    public static function requestMatchesAllowed(\Illuminate\Http\Request $request, array $allowed): bool
    {
        if ($allowed === []) {
            return false;
        }

        $clientIp = trim((string) $request->ip());

        return $clientIp !== '' && self::clientMatchesAllowedHosts($clientIp, $allowed);
    }

    /**
     * @param list<string> $allowed
     */
    public static function clientMatchesAllowedHosts(string $clientIp, array $allowed): bool
    {
        $clientIp = trim($clientIp);
        if ($clientIp === '' || $allowed === []) {
            return false;
        }

        foreach ($allowed as $url) {
            $host = self::hostOf($url);
            if ($host === null) {
                continue;
            }

            if (self::ipMatchesHost($clientIp, $host)) {
                return true;
            }
        }

        return false;
    }

    public static function hostOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));

        return $host !== '' ? $host : null;
    }

    private static function ipMatchesHost(string $ip, string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $ip === $host;
        }

        if ($host === 'localhost' && in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $recordIp = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($recordIp) && $recordIp === $ip) {
                    return true;
                }
            }
        }

        $resolved = @gethostbyname($host);
        if ($resolved !== $host && $resolved === $ip) {
            return true;
        }

        return false;
    }

    /**
     * @param list<string> $allowed
     */
    public static function requiresCallerUrl(array $allowed): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed>|null $manifest
     */
    public static function hasApiUrls(?array $manifest): bool
    {
        return self::fromManifest($manifest) !== [];
    }

    /**
     * @deprecated Publish no longer requires api_urls — only apps that call bridge HTTP need them.
     * @param array<string, mixed>|null $manifest
     */
    public static function assertRequired(?array $manifest): void
    {
        if (self::fromManifest($manifest) === []) {
            throw new \RuntimeException('manifest.json: api_urls is required (at least one https/http URL for your tool backend)');
        }
    }

    private static function matchesEntry(string $candidate, string $allowed): bool
    {
        if ($candidate === $allowed) {
            return true;
        }

        $allowedOrigin = self::originOf($allowed);
        $candidateOrigin = self::originOf($candidate);
        if ($allowedOrigin === null || $candidateOrigin === null) {
            return false;
        }

        if ($allowed === $allowedOrigin) {
            return $candidateOrigin === $allowedOrigin;
        }

        if (!str_starts_with($allowed, $allowedOrigin)) {
            return false;
        }

        return str_starts_with($candidate, $allowed)
            || str_starts_with($allowed, $candidate);
    }

    /**
     * CSP connect-src origins for publisher backends declared in manifest api_urls.
     *
     * @param list<string> $allowed
     * @return list<string>
     */
    public static function connectSrcOrigins(array $allowed): array
    {
        $origins = [];
        foreach ($allowed as $url) {
            $origin = self::originOf($url);
            if ($origin !== null && !in_array($origin, $origins, true)) {
                $origins[] = $origin;
            }
        }

        $expanded = $origins;
        foreach ($origins as $origin) {
            if (str_starts_with($origin, 'http://localhost:')) {
                $alias = 'http://127.0.0.1' . substr($origin, strlen('http://localhost'));
                if (!in_array($alias, $expanded, true)) {
                    $expanded[] = $alias;
                }
            } elseif (str_starts_with($origin, 'https://localhost:')) {
                $alias = 'https://127.0.0.1' . substr($origin, strlen('https://localhost'));
                if (!in_array($alias, $expanded, true)) {
                    $expanded[] = $alias;
                }
            }
        }

        return $expanded;
    }

    private static function originOf(string $url): ?string
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
        if ($port !== null && !self::isDefaultPort($scheme, $port)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
    }
}
