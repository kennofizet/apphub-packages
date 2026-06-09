<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Launch\Http\Controllers\LaunchController;

Route::post('verify-launch-token', [LaunchController::class, 'verifyLaunchToken']);
