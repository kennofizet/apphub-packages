<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\BridgeController;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\IntegrationDocsController;

Route::get('integration-docs', [IntegrationDocsController::class, 'publisher']);
Route::post('bridge/scopes', [BridgeController::class, 'grantScope']);
