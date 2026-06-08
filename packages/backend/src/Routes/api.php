<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Controllers\AppHubController;
use Kennofizet\AppHub\Controllers\BridgeController;

$prefix = config('packages-core.api_prefix', 'api/knf');
$hubPrefix = config('apphub.api_prefix', 'apphub');
$rateLimit = config('packages-core.rate_limit', 60);

$baseMiddleware = ['api', "throttle:{$rateLimit},1", 'knf.core.token', 'knf.core.validator'];
$hostMiddleware = ['api', "throttle:{$rateLimit},1", 'knf.core.token', 'knf.core.validator', 'apphub.host.access'];
$bridgeMiddleware = ['api', "throttle:{$rateLimit},1", 'apphub.launch.token', 'knf.core.validator'];

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($baseMiddleware)
    ->group(function () {
        Route::get('bootstrap', [AppHubController::class, 'bootstrap']);
        Route::get('apps', [AppHubController::class, 'apps']);
        Route::get('apps/{slug}/launch', [AppHubController::class, 'launch'])
            ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
        Route::get('integration-docs', [AppHubController::class, 'integrationDocs']);
        Route::post('bridge/scopes', [BridgeController::class, 'grantScope']);
    });

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($hostMiddleware)
    ->group(function () {
        Route::get('integration-docs/internal', [AppHubController::class, 'integrationDocsInternal']);
    });

Route::prefix($prefix . '/' . $hubPrefix)
    ->middleware($bridgeMiddleware)
    ->group(function () {
        Route::get('bridge/user', [BridgeController::class, 'user']);
        Route::post('bridge/desktop/message', [BridgeController::class, 'desktopMessage']);
    });
