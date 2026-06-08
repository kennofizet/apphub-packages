<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class LaunchTokenService
{
    private const CACHE_PREFIX = 'apphub:launch:';

    public function mint(string $slug, ?string $userId = null): array
    {
        $token = Str::random(64);
        $sessionId = (string) Str::uuid();

        $payload = [
            'app_slug' => $slug,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'scopes_granted' => [],
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_PREFIX . $token, $payload, $this->ttl());

        return [
            'launch_token' => $token,
            'session_id' => $sessionId,
            'scopes_granted' => [],
        ];
    }

    public function resolve(string $token, string $appSlug): ?array
    {
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{32,128}$/', $token)) {
            return null;
        }

        $payload = Cache::get(self::CACHE_PREFIX . $token);
        if (!is_array($payload)) {
            return null;
        }

        if (($payload['app_slug'] ?? '') !== $appSlug) {
            return null;
        }

        return $payload;
    }

    public function peek(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{32,128}$/', $token)) {
            return null;
        }

        $payload = Cache::get(self::CACHE_PREFIX . $token);

        return is_array($payload) ? $payload : null;
    }

    public function grantScope(string $token, string $scope, string $userId): bool
    {
        $key = self::CACHE_PREFIX . $token;
        $payload = Cache::get($key);
        if (!is_array($payload)) {
            return false;
        }

        if ((string) ($payload['user_id'] ?? '') !== $userId) {
            return false;
        }

        $scopes = $payload['scopes_granted'] ?? [];
        if (!is_array($scopes)) {
            $scopes = [];
        }

        if (!in_array($scope, $scopes, true)) {
            $scopes[] = $scope;
        }

        $payload['scopes_granted'] = $scopes;
        Cache::put($key, $payload, $this->ttl());

        return true;
    }

    public function hasScope(array $payload, string $scope): bool
    {
        $scopes = $payload['scopes_granted'] ?? [];

        return is_array($scopes) && in_array($scope, $scopes, true);
    }

    private function ttl(): \DateTimeInterface
    {
        $seconds = max(60, (int) config('apphub.launch_token_ttl', 900));

        return now()->addSeconds($seconds);
    }
}
