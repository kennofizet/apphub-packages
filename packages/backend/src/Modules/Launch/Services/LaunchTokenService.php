<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Support\Str;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Launch\Models\AppLaunchToken;

final class LaunchTokenService
{
    public function mint(App $app, int $userId, ?string $ip = null, ?string $userAgent = null): array
    {
        $plainToken = Str::random(64);
        $sessionId = (string) Str::uuid();

        AppLaunchToken::query()->create([
            'app_id' => $app->id,
            'user_id' => $userId,
            'token_hash' => $this->hashToken($plainToken),
            'session_id' => $sessionId,
            'scopes_granted' => [],
            'expires_at' => now()->addSeconds($this->ttlSeconds()),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [
            'launch_token' => $plainToken,
            'session_id' => $sessionId,
            'scopes_granted' => [],
        ];
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

    public function peek(string $token): ?array
    {
        $record = $this->findByPlainToken($token);
        if ($record === null || $record->isExpired()) {
            return null;
        }

        $record->loadMissing('app');

        return $this->toPayload($record);
    }

    public function grantScope(string $token, string $scope, string $userId): bool
    {
        $record = $this->findByPlainToken($token);
        if ($record === null || $record->isExpired()) {
            return false;
        }

        if ((string) $record->user_id !== $userId) {
            return false;
        }

        $scopes = is_array($record->scopes_granted) ? $record->scopes_granted : [];
        if (!in_array($scope, $scopes, true)) {
            $scopes[] = $scope;
        }

        $record->scopes_granted = $scopes;
        $record->save();

        return true;
    }

    public function hasScope(array $payload, string $scope): bool
    {
        $scopes = $payload['scopes_granted'] ?? [];

        return is_array($scopes) && in_array($scope, $scopes, true);
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
        ];
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function findByPlainToken(string $token): ?AppLaunchToken
    {
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{32,128}$/', $token)) {
            return null;
        }

        return AppLaunchToken::query()
            ->where('token_hash', $this->hashToken($token))
            ->first();
    }

    /** @return array{app_slug: string, session_id: string|null, user_id: int, scopes_granted: list<string>} */
    private function toPayload(AppLaunchToken $record): array
    {
        return [
            'app_slug' => (string) ($record->app?->slug ?? ''),
            'session_id' => $record->session_id,
            'user_id' => (int) $record->user_id,
            'scopes_granted' => is_array($record->scopes_granted) ? $record->scopes_granted : [],
        ];
    }

    private function ttlSeconds(): int
    {
        $min = max(60, (int) config('apphub.launch_token_ttl_min', 60));
        $max = max($min, (int) config('apphub.launch_token_ttl_max', 180));
        $configured = (int) config('apphub.launch_token_ttl', 180);

        return max($min, min($max, $configured));
    }
}
