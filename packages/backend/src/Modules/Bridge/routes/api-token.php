<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\BridgeController;

Route::get('bridge/user', [BridgeController::class, 'user']);
Route::post('bridge/notify', [BridgeController::class, 'notify'])
    ->middleware('throttle:apphub-bridge-notify');
