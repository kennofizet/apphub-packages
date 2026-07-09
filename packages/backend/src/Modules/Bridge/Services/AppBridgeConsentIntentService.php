<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Services;

use Illuminate\Support\Str;
use Kennofizet\AppHub\Modules\Bridge\Models\AppBridgeConsentIntent;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;

final class AppBridgeConsentIntentService
{
    /**
     * @return array{intent_token: string, expires_in: int}
     */
    public function createIntent(App $app, int $userId, ?string $bundleVersion): array
    {
        AppBridgeConsentIntent::query()
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $plainToken = Str::random(48);
        $ttl = max(30, (int) config('apphub.install_intent_ttl_seconds', 120));

        AppBridgeConsentIntent::query()->create([
            'app_id' => $app->id,
            'user_id' => $userId,
            'token_hash' => $this->hashToken($plainToken),
            'bundle_version' => $this->normalizeBundleVersion($bundleVersion),
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return [
            'intent_token' => $plainToken,
            'expires_in' => $ttl,
        ];
    }

    public function consumeIntent(string $plainToken, App $app, int $userId, ?string $bundleVersion = null): bool
    {
        $token = trim($plainToken);
        if (strlen($token) < 32) {
            return false;
        }

        $record = AppBridgeConsentIntent::query()
            ->where('token_hash', $this->hashToken($token))
            ->where('app_id', $app->id)
            ->where('user_id', $userId)
            ->first();

        if ($record === null || $record->isExpired() || $record->isUsed()) {
            return false;
        }

        if (!$this->bundleVersionsMatch($record->bundle_version, $bundleVersion)) {
            return false;
        }

        $record->used_at = now();
        $record->save();

        return true;
    }

    /** Invalidate unused install intents after a draft re-submit (force a fresh dialog). */
    public function invalidateOpenIntentsForApp(App $app): int
    {
        return AppBridgeConsentIntent::query()
            ->where('app_id', $app->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function normalizeBundleVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $trimmed = trim($version);
        if ($trimmed === '') {
            return null;
        }

        $normalized = AppSemver::normalize($trimmed);

        return $normalized !== '' && AppSemver::isValid($normalized) ? $normalized : null;
    }

    private function bundleVersionsMatch(?string $intentVersion, ?string $requestVersion): bool
    {
        $intent = $this->normalizeBundleVersion($intentVersion);
        $request = $this->normalizeBundleVersion($requestVersion);

        return $intent === $request;
    }
}
