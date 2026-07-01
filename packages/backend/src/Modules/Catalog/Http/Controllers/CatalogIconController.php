<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Http\Controllers;

use Illuminate\Http\Response;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppIconStorageService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;

class CatalogIconController extends Controller
{
    public function __construct(private readonly AppIconStorageService $icons)
    {
    }

    public function show(string $slug): Response
    {
        $slug = strtolower(trim($slug));
        $app = App::query()->where('slug', $slug)->first();
        if ($app === null || AppStatus::isDisabled((string) $app->status)) {
            abort(404);
        }

        $assetPath = trim((string) ($app->icon_asset_path ?? ''));
        if ($assetPath === '') {
            abort(404);
        }

        try {
            $absolute = $this->icons->absolutePath($assetPath);
        } catch (\Throwable) {
            abort(404);
        }

        if (!is_file($absolute)) {
            abort(404);
        }

        return response()->file($absolute, [
            'Content-Type' => $this->icons->mimeType($assetPath),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
