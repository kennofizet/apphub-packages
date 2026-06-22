<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\BridgeConsentController;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\BridgeController;

Route::post('apps/{slug}/install-intent', [BridgeConsentController::class, 'createIntent'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
Route::post('apps/{slug}/bridge-consents', [BridgeConsentController::class, 'store'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
