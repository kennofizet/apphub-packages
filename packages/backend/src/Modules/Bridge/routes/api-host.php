<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\IntegrationDocsController;

Route::get('integration-docs/internal', [IntegrationDocsController::class, 'internal']);
