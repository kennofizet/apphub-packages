<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogController;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogDevController;

Route::get('bootstrap', [CatalogController::class, 'bootstrap']);
Route::get('apps', [CatalogController::class, 'apps']);

Route::prefix('dev')->group(function (): void {
    Route::get('apps', [CatalogDevController::class, 'apps']);
    Route::post('apps/{slug}/status', [CatalogDevController::class, 'updateStatus'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
    Route::post('apps/{slug}/disable', [CatalogDevController::class, 'disable'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
});
