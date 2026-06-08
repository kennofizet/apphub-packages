<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host integrator routes only — not zone/server managers from packages-core.
 * Those are regular users with settings permission in other packages, not App Hub host devs.
 */
final class EnsureAppHubHostAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('apphub.host_access_secret', '');

        if ($secret === '') {
            return response()->json([
                'success' => false,
                'error' => 'Host access not configured (set APPHUB_HOST_ACCESS_SECRET)',
            ], 503);
        }

        $provided = (string) $request->header('X-AppHub-Host-Access', '');
        if ($provided === '' || !hash_equals($secret, $provided)) {
            return response()->json(['success' => false, 'error' => 'Host access required'], 403);
        }

        return $next($request);
    }
}
