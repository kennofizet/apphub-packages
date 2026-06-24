<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$prefix = config('packages-core.api_prefix', 'api/knf');
$hubPrefix = config('apphub.api_prefix', 'apphub');
$rateLimit = config('packages-core.rate_limit', 60);

$baseMiddleware = ['api', "throttle:{$rateLimit},1", 'knf.core.token', 'knf.core.validator'];
$hostMiddleware = ['api', "throttle:{$rateLimit},1", 'knf.core.token', 'knf.core.validator', 'apphub.host.access'];
$bridgeMiddleware = ['api', 'throttle:apphub-bridge', 'apphub.launch.token', 'knf.core.validator'];
$publicMiddleware = ['api', "throttle:{$rateLimit},1", 'knf.core.validator'];
$verifyLaunchMiddleware = ['api', 'throttle:apphub-verify-launch', 'knf.core.validator'];

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($baseMiddleware)
    ->group(function (): void {
        require __DIR__ . '/../Modules/Catalog/routes/api.php';
        require __DIR__ . '/../Modules/Launch/routes/api.php';
        require __DIR__ . '/../Modules/Bridge/routes/api-base.php';
    });

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($hostMiddleware)
    ->group(function (): void {
        require __DIR__ . '/../Modules/Bridge/routes/api-host.php';
    });

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($bridgeMiddleware)
    ->group(function (): void {
        require __DIR__ . '/../Modules/Bridge/routes/api-token.php';
    });

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($publicMiddleware)
    ->group(function (): void {
        require __DIR__ . '/../Modules/Launch/routes/api-public.php';
        require __DIR__ . '/../Modules/Catalog/routes/api-runtime.php';
        require __DIR__ . '/../Modules/Bridge/routes/api-public.php';
    });

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($verifyLaunchMiddleware)
    ->group(function (): void {
        Route::post('verify-launch-token', [\Kennofizet\AppHub\Modules\Launch\Http\Controllers\LaunchController::class, 'verifyLaunchToken']);
    });
