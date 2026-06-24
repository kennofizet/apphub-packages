<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Launch\Http\Controllers\LaunchController;
use Kennofizet\AppHub\Modules\Launch\Http\Controllers\UserNotificationController;

Route::post('apps/{slug}/launch', [LaunchController::class, 'launch'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');

Route::post('apps/{slug}/ping', [LaunchController::class, 'ping'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');

Route::post('apps/{slug}/usage', [LaunchController::class, 'usage'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');

Route::get('notifications', [UserNotificationController::class, 'index']);
Route::get('notifications/summary', [UserNotificationController::class, 'summary']);
Route::post('notifications/dismiss', [UserNotificationController::class, 'dismiss']);
Route::post('notifications/read-all', [UserNotificationController::class, 'readAll']);
