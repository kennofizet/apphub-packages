<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Services;

use Illuminate\Support\Str;
use Kennofizet\AppHub\Modules\Bridge\Models\AppBridgeConsentIntent;
use Kennofizet\AppHub\Modules\Catalog\Models\App;

final class AppBridgeConsentIntentService
{
    /**
     * @return array{intent_token: string, expires_in: int}
     */
    public function createIntent(App $app, int $userId, ?string $bundleVersion): array
    {
        $plainToken = Str::random(48);
        $ttl = max(30, (int) config('apphub.install_intent_ttl_seconds', 120));

        AppBridgeConsentIntent::query()->create([
            'app_id' => $app->id,
            'user_id' => $userId,
            'token_hash' => $this->hashToken($plainToken),
            'bundle_version' => $bundleVersion !== null && trim($bundleVersion) !== '' ? trim($bundleVersion) : null,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return [
            'intent_token' => $plainToken,
            'expires_in' => $ttl,
        ];
    }

    public function consumeIntent(string $plainToken, App $app, int $userId): bool
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

        $record->used_at = now();
        $record->save();

        return true;
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
