<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\AppHub\Modules\Bridge\Http\Controllers\IntegrationDocsController;

/** Publisher contract JSON — public, no token (AI tools + Guide link). */
Route::get('integration-docs', [IntegrationDocsController::class, 'publisher']);
