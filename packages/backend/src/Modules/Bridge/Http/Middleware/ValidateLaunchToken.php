<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Modules\Launch\Services\AppLaunchCallerUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;
use Symfony\Component\HttpFoundation\Response;

final class ValidateLaunchToken
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(
        private readonly LaunchTokenService $launchTokens,
        private readonly AppLaunchCallerUrlGuard $callerUrlGuard,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->header('X-AppHub-Launch-Token', '');
        $slug = (string) $request->header('X-AppHub-App-Slug', '');

        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug header'], 422);
        }

        $record = $this->launchTokens->recordForGrant($token);
        if ($record === null || $record->app === null || $record->app->slug !== $slug) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired launch token'], 401);
        }

        $payload = $this->launchTokens->resolve($token, $slug);
        if ($payload === null) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired launch token'], 401);
        }

        $tokenSessionId = trim((string) ($record->session_id ?? ''));
        if ($tokenSessionId !== '') {
            $requestSessionId = trim((string) $request->header('X-AppHub-Session-Id', ''));
            if ($requestSessionId === '' || !hash_equals($tokenSessionId, $requestSessionId)) {
                return response()->json(['success' => false, 'error' => 'Invalid launch session'], 401);
            }
        }

        $guard = $this->callerUrlGuard->validate(
            $record->app,
            $payload['bundle_version'] ?? null,
            $request,
            $payload,
        );
        if ($guard['ok'] !== true) {
            return response()->json(['success' => false, 'error' => $guard['error']], $guard['status']);
        }

        $request->attributes->set('apphub_launch', $payload);
        $request->attributes->set('apphub_launch_token_hash', $this->launchTokens->hashToken($token));
        if ($record->expires_at !== null) {
            $request->attributes->set('apphub_launch_expires_at', $record->expires_at);
        }

        return $next($request);
    }
}
