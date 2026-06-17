<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppRuntimeServeService;
use Symfony\Component\HttpFoundation\Response;

class RuntimeController extends Controller
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppRuntimeServeService $runtime,
    ) {
    }

    public function serve(Request $request, string $slug, string $path = ''): Response
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return new Response('Invalid slug', 422);
        }

        $app = $this->catalog->findBySlug($slug);
        if ($app === null) {
            return new Response('App not found', 404);
        }

        return $this->runtime->serve($app, $path, $request);
    }
}
