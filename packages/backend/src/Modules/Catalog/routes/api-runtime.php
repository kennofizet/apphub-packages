<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Catalog\Http\Controllers\RuntimeController;

Route::match(['GET', 'HEAD', 'OPTIONS'], 'apps/{slug}/runtime/{path}', [RuntimeController::class, 'serve'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}')
    ->where('path', '.*')
    ->defaults('path', 'index.html');

Route::match(['GET', 'HEAD', 'OPTIONS'], 'apps/{slug}/icon', [\Kennofizet\AppHub\Modules\Catalog\Http\Controllers\CatalogIconController::class, 'show'])
    ->where('slug', '[a-z0-9][a-z0-9_-]{0,63}');
