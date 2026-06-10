<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogController;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogDevController;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogPublishController;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogVersionController;

Route::get('bootstrap', [CatalogController::class, 'bootstrap']);
Route::get('apps', [CatalogController::class, 'apps']);

Route::post('apps/register', [CatalogPublishController::class, 'register']);
Route::get('apps/{slug}/versions', [CatalogVersionController::class, 'index'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');

Route::prefix('dev')->group(function (): void {
    Route::get('apps', [CatalogDevController::class, 'apps']);
    Route::get('apps/{slug}/bundle-inspect', [CatalogDevController::class, 'inspectBundle'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
    Route::post('apps/{slug}/status', [CatalogDevController::class, 'updateStatus'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
    Route::post('apps/{slug}/disable', [CatalogDevController::class, 'disable'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
});
