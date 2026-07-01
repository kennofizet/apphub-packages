<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class AppIconStorageService
{
    /** @var list<string> */
    private const ALLOWED_EXT = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

    private const MAX_BYTES = 524_288;

    public function storeFromUpload(string $slug, UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new RuntimeException('Invalid icon upload');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Icon exceeds maximum size (512 KB)');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new RuntimeException('Icon must be PNG, JPG, SVG, or WebP');
        }

        $contents = (string) file_get_contents($file->getRealPath());
        $this->assertImageContents($contents, $ext);

        return $this->writeIcon($slug, $ext, $contents);
    }

    public function storeFromBundleFile(string $slug, string $bundlePath, string $relativeFile): ?string
    {
        $relativeFile = ltrim(str_replace('\\', '/', trim($relativeFile)), '/');
        if ($relativeFile === '' || str_contains($relativeFile, '..')) {
            return null;
        }

        $ext = strtolower(pathinfo($relativeFile, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return null;
        }

        try {
            $absolute = app(AppBundleStorageService::class)->absolutePath($bundlePath, $relativeFile);
        } catch (RuntimeException) {
            return null;
        }

        if (!is_file($absolute)) {
            return null;
        }

        $size = (int) filesize($absolute);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return null;
        }

        $contents = (string) file_get_contents($absolute);
        $this->assertImageContents($contents, $ext);

        return $this->writeIcon($slug, $ext, $contents);
    }

    public function storeFromDataUrl(string $slug, string $dataUrl): ?string
    {
        $dataUrl = trim($dataUrl);
        if (!preg_match('#^data:image/(png|jpe?g|svg\+xml|webp);base64,(.+)$#i', $dataUrl, $matches)) {
            return null;
        }

        $mime = strtolower($matches[1]);
        $ext = match ($mime) {
            'png' => 'png',
            'jpg', 'jpeg' => 'jpg',
            'svg+xml' => 'svg',
            'webp' => 'webp',
            default => null,
        };

        if ($ext === null) {
            return null;
        }

        $decoded = base64_decode($matches[2], true);
        if ($decoded === false || strlen($decoded) > self::MAX_BYTES) {
            return null;
        }

        $this->assertImageContents($decoded, $ext);

        return $this->writeIcon($slug, $ext, $decoded);
    }

    public function deleteIcon(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        Storage::disk($this->disk())->delete($relativePath);
    }

    public function mimeType(string $relativePath): string
    {
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    public function absolutePath(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new RuntimeException('Invalid icon path');
        }

        return Storage::disk($this->disk())->path($relativePath);
    }

    private function writeIcon(string $slug, string $ext, string $contents): string
    {
        $root = trim((string) config('apphub.icon_storage_root', 'apphub/icons'), '/');
        $relative = $root . '/' . $this->normalizeSlug($slug) . '.' . $ext;
        Storage::disk($this->disk())->put($relative, $contents);

        return $relative;
    }

    private function assertImageContents(string $contents, string $ext): void
    {
        if ($contents === '') {
            throw new RuntimeException('Icon file is empty');
        }

        if ($ext === 'svg') {
            if (!str_contains($contents, '<svg')) {
                throw new RuntimeException('Invalid SVG icon');
            }
            if (preg_match('/<(script|foreignObject)\b/i', $contents)) {
                throw new RuntimeException('SVG icon must not contain script or foreignObject');
            }
        }
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            throw new RuntimeException('Invalid app slug for icon storage');
        }

        return $slug;
    }

    private function disk(): string
    {
        return (string) config('apphub.bundle_disk', 'local');
    }
}
