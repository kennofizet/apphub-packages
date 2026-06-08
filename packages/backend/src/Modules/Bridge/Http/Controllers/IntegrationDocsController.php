<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kennofizet\AppHub\Modules\Bridge\Support\IntegrationDocs;

class IntegrationDocsController extends Controller
{
    public function publisher(): JsonResponse
    {
        try {
            $doc = IntegrationDocs::read();

            return response()->json(IntegrationDocs::forPublisher($doc));
        } catch (\JsonException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 500);
        } catch (\RuntimeException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 404);
        }
    }

    /** Full contract for host integrators only — same auth, not for publisher apps. */
    public function internal(): JsonResponse
    {
        try {
            return response()->json(IntegrationDocs::read());
        } catch (\JsonException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 500);
        } catch (\RuntimeException) {
            return response()->json(['success' => false, 'error' => 'Integration documentation unavailable'], 404);
        }
    }
}
