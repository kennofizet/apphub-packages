<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Support\Str;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Launch\Models\AppLaunchToken;

final class LaunchTokenService
{
    public function mint(
        App $app,
        int $userId,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $bundleVersion = null,
        array $initialScopes = [],
    ): array {
        $plainToken = Str::random(64);
        $sessionId = (string) Str::uuid();
        $bundleVersion = $bundleVersion !== null ? trim($bundleVersion) : null;
        if ($bundleVersion === '') {
            $bundleVersion = null;
        }

        $scopesGranted = $this->normalizeScopes($initialScopes);

        AppLaunchToken::query()->create([
            'app_id' => $app->id,
            'user_id' => $userId,
            'token_hash' => $this->hashToken($plainToken),
            'session_id' => $sessionId,
            'bundle_version' => $bundleVersion,
            'scopes_granted' => $scopesGranted,
            'expires_at' => now()->addSeconds($this->ttlSeconds()),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [
            'launch_token' => $plainToken,
            'session_id' => $sessionId,
            'scopes_granted' => $scopesGranted,
            'expires_in' => $this->ttlSeconds(),
        ];
    }

    /**
     * Rotate launch_token for an existing session while Hub user auth is still valid.
     * Keeps session_id; replaces token hash (short theft window) and scopes_granted from live consent.
     *
     * @param list<string> $scopesGranted Fresh scopes from AppBridgeConsentService::scopesForLaunch
     * @return array{launch_token: string, session_id: string, scopes_granted: list<string>, expires_in: int, bundle_version: string|null}|null
     */
    public function refreshForUser(
        App $app,
        int $userId,
        string $sessionId,
        array $scopesGranted = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): ?array {
        $record = $this->findSessionForRefresh($userId, (string) $app->slug, $sessionId);
        if ($record === null) {
            return null;
        }

        $scopes = $this->normalizeScopes($scopesGranted);

        $plainToken = Str::random(64);
        $record->token_hash = $this->hashToken($plainToken);
        $record->expires_at = now()->addSeconds($this->ttlSeconds());
        $record->scopes_granted = $scopes;
        $record->used_at = null;
        if ($ip !== null && $ip !== '') {
            $record->ip = $ip;
        }
        if ($userAgent !== null && $userAgent !== '') {
            $record->user_agent = $userAgent;
        }
        $record->save();

        $bundleVersion = $record->bundle_version !== null ? trim((string) $record->bundle_version) : '';

        return [
            'launch_token' => $plainToken,
            'session_id' => (string) $record->session_id,
            'scopes_granted' => $scopes,
            'expires_in' => $this->ttlSeconds(),
            'bundle_version' => $bundleVersion !== '' ? $bundleVersion : null,
        ];
    }

    /**
     * Drop launch sessions for a user+app so refresh and token validation stop after consent revoke.
     */
    public function invalidateSessionsForUser(App $app, int $userId): int
    {
        if ($userId < 1 || (int) ($app->id ?? 0) < 1) {
            return 0;
        }

        return AppLaunchToken::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Drop all launch sessions for an app (every user) — used when app-wide consents are cleared.
     */
    public function invalidateSessionsForApp(App $app): int
    {
        if ((int) ($app->id ?? 0) < 1) {
            return 0;
        }

        return AppLaunchToken::query()
            ->where('app_id', $app->id)
            ->delete();
    }

    /**
     * Session eligible for Hub-authenticated refresh (may be past short token TTL,
     * but still within absolute session max lifetime).
     */
    public function findSessionForRefresh(int $userId, string $appSlug, string $sessionId): ?AppLaunchToken
    {
        if ($userId < 1 || trim($sessionId) === '' || !preg_match(self::SLUG_PATTERN, $appSlug)) {
            return null;
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sessionId)) {
            return null;
        }

        $record = AppLaunchToken::query()
            ->where('session_id', trim($sessionId))
            ->where('user_id', $userId)
            ->whereHas('app', static function ($query) use ($appSlug): void {
                $query->where('slug', $appSlug);
            })
            ->orderByDesc('id')
            ->first();

        if ($record === null || $record->created_at === null) {
            return null;
        }

        if ($record->created_at->lt(now()->subSeconds($this->sessionMaxTtlSeconds()))) {
            return null;
        }

        return $record;
    }

    public function resolve(string $token, string $appSlug): ?array
    {
        $record = $this->findByPlainToken($token);
        if ($record === null || $record->isExpired()) {
            return null;
        }

        $record->loadMissing('app');
        if ($record->app === null || $record->app->slug !== $appSlug) {
            return null;
        }

        return $this->toPayload($record);
    }

    /** Runtime asset cookie auth — token_hash + slug, not expired. */
    public function isValidHashForSlug(string $tokenHash, string $appSlug): bool
    {
        return $this->findValidForRuntimeByHash($tokenHash, $appSlug) !== null;
    }

    public function findValidForRuntimeByPlainToken(string $token, string $appSlug): ?AppLaunchToken
    {
        $record = $this->findByPlainToken($token);
        if ($record === null || $record->isExpired()) {
            return null;
        }

        $record->loadMissing('app');
        if ($record->app === null || $record->app->slug !== $appSlug) {
            return null;
        }

        return $record;
    }

    public function findValidForRuntimeByHash(string $tokenHash, string $appSlug): ?AppLaunchToken
    {
        if ($tokenHash === '' || !preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
            return null;
        }

        $record = AppLaunchToken::query()
            ->where('token_hash', $tokenHash)
            ->whereHas('app', static function ($query) use ($appSlug): void {
                $query->where('slug', $appSlug);
            })
            ->first();

        if ($record === null || $record->isExpired()) {
            return null;
        }

        return $record;
    }

    public function peek(string $token): ?array
    {
        unset($token);

        return null;
    }

    public function recordForGrant(string $token): ?AppLaunchToken
    {
        $record = $this->findByPlainToken($token);
        if ($record === null || $record->isExpired()) {
            return null;
        }

        $record->loadMissing('app');

        return $record->app !== null ? $record : null;
    }

    public function hasScope(array $payload, string $scope): bool
    {
        $scopes = $payload['scopes_granted'] ?? [];

        return is_array($scopes) && in_array($scope, $scopes, true);
    }

    /** @param array<string, mixed> $payload */
    public function hasUserReadAccess(array $payload): bool
    {
        return $this->hasScope($payload, 'user.read')
            || $this->hasScope($payload, 'user.profile');
    }

    /** Tool backend: one-time verify; marks used_at. */
    public function verify(string $token, ?string $appSlug = null): ?array
    {
        $record = $this->findByPlainToken($token);
        if ($record === null || $record->isExpired()) {
            return null;
        }

        if ($record->isUsed()) {
            return null;
        }

        $record->loadMissing('app');
        if ($record->app === null) {
            return null;
        }

        if ($appSlug !== null && $appSlug !== '' && $record->app->slug !== $appSlug) {
            return null;
        }

        $record->used_at = now();
        $record->save();

        return [
            'user_id' => (int) $record->user_id,
            'app_slug' => $record->app->slug,
            'session_id' => $record->session_id,
            'scopes_granted' => is_array($record->scopes_granted) ? $record->scopes_granted : [],
            'bundle_version' => $record->bundle_version !== null && trim((string) $record->bundle_version) !== ''
                ? trim((string) $record->bundle_version)
                : null,
        ];
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function findActiveSessionForUser(int $userId, string $appSlug, string $sessionId): ?AppLaunchToken
    {
        if ($userId < 1 || trim($sessionId) === '' || !preg_match(self::SLUG_PATTERN, $appSlug)) {
            return null;
        }

        $record = AppLaunchToken::query()
            ->where('session_id', trim($sessionId))
            ->where('user_id', $userId)
            ->whereHas('app', static function ($query) use ($appSlug): void {
                $query->where('slug', $appSlug);
            })
            ->orderByDesc('id')
            ->first();

        if ($record === null || $record->isExpired()) {
            return null;
        }

        return $record;
    }

    public function sessionHasScope(AppLaunchToken $record, string $scope): bool
    {
        $scopes = is_array($record->scopes_granted) ? $record->scopes_granted : [];

        return in_array($scope, $scopes, true);
    }

    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    private function findByPlainToken(string $token): ?AppLaunchToken
    {
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{32,128}$/', $token)) {
            return null;
        }

        return AppLaunchToken::query()
            ->where('token_hash', $this->hashToken($token))
            ->first();
    }

    /** @return array{app_slug: string, session_id: string|null, user_id: int, scopes_granted: list<string>, bundle_version: string|null} */
    private function toPayload(AppLaunchToken $record): array
    {
        $bundleVersion = $record->bundle_version !== null ? trim((string) $record->bundle_version) : '';

        return [
            'app_slug' => (string) ($record->app?->slug ?? ''),
            'session_id' => $record->session_id,
            'user_id' => (int) $record->user_id,
            'scopes_granted' => is_array($record->scopes_granted) ? $record->scopes_granted : [],
            'bundle_version' => $bundleVersion !== '' ? $bundleVersion : null,
        ];
    }

    private function ttlSeconds(): int
    {
        $min = max(60, (int) config('apphub.launch_token_ttl_min', 60));
        $max = max($min, (int) config('apphub.launch_token_ttl_max', 180));
        $configured = (int) config('apphub.launch_token_ttl', 180);

        return max($min, min($max, $configured));
    }

    private function sessionMaxTtlSeconds(): int
    {
        $tokenTtl = $this->ttlSeconds();

        return max($tokenTtl, (int) config('apphub.launch_session_max_ttl', 28_800));
    }

    /**
     * @param list<string> $scopes
     * @return list<string>
     */
    private function normalizeScopes(array $scopes): array
    {
        $out = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                continue;
            }
            $scope = trim($scope);
            if ($scope === '' || in_array($scope, $out, true)) {
                continue;
            }
            $out[] = $scope;
        }

        return $out;
    }
}
