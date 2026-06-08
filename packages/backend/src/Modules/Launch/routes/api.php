<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Launch\Http\Controllers\LaunchController;

Route::get('apps/{slug}/launch', [LaunchController::class, 'launch'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
