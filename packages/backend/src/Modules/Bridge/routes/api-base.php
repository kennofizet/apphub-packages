<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\BridgeConsentController;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\BridgeController;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\ParentBridgeController;

Route::post('apps/{slug}/install-intent', [BridgeConsentController::class, 'createIntent'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
Route::post('apps/{slug}/bridge-consents', [BridgeConsentController::class, 'store'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
Route::delete('apps/{slug}/bridge-consents', [BridgeConsentController::class, 'destroy'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');

Route::middleware('throttle:apphub-parent-bridge')->group(function (): void {
    Route::post('parent-bridge/call', [ParentBridgeController::class, 'call']);
    Route::post('parent-bridge/event', [ParentBridgeController::class, 'event']);
});
