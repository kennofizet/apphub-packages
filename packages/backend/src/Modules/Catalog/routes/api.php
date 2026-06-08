<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogController;

Route::get('bootstrap', [CatalogController::class, 'bootstrap']);
Route::get('apps', [CatalogController::class, 'apps']);
