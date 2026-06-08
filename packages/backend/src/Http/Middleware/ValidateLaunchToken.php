<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Support\LaunchTokenService;
use Symfony\Component\HttpFoundation\Response;

final class ValidateLaunchToken
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(private readonly LaunchTokenService $launchTokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->header('X-AppHub-Launch-Token', '');
        $slug = (string) $request->header('X-AppHub-App-Slug', '');

        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return response()->json(['success' => false, 'error' => 'Invalid app slug header'], 422);
        }

        $payload = $this->launchTokens->resolve($token, $slug);
        if ($payload === null) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired launch token'], 401);
        }

        $request->attributes->set('apphub_launch', $payload);

        return $next($request);
    }
}
