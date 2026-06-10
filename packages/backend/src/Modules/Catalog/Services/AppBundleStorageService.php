<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class AppBundleStorageService
{
    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'ps1', 'dll', 'so', 'dylib',
    ];

    /**
     * @return array{path: string, hash: string, entry: string, file_count: int}
     */
    public function storeFromZip(string $slug, UploadedFile $zip, string $entry = 'index.html'): array
    {
        $this->assertZipUpload($zip);

        $tempDir = $this->extractZipToTemp($zip);
        try {
            $this->validateExtractedTree($tempDir, $entry);

            return $this->moveTreeToBundlePath($slug, $tempDir, $entry);
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * @return array{path: string, hash: string, entry: string, file_count: int}
     */
    public function storeFromDirectory(string $slug, string $sourceDir, string $entry = 'index.html'): array
    {
        $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');
        if (!is_dir($sourceDir)) {
            throw new RuntimeException('Bundle source directory not found');
        }

        $tempDir = sys_get_temp_dir() . '/apphub-bundle-' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Could not create temp directory');
        }

        $this->copyDirectory($sourceDir, $tempDir);
        try {
            $this->validateExtractedTree($tempDir, $entry);

            return $this->moveTreeToBundlePath($slug, $tempDir, $entry);
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    public function deleteBundle(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        Storage::disk($this->disk())->deleteDirectory($relativePath);
    }

    public function absolutePath(string $relativePath, string $file): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $file = ltrim(str_replace('\\', '/', $file), '/');
        $full = $relativePath . '/' . $file;

        if (str_contains($full, '..')) {
            throw new RuntimeException('Invalid bundle path');
        }

        return Storage::disk($this->disk())->path($full);
    }

    public function disk(): string
    {
        return (string) config('apphub.bundle_disk', 'local');
    }

    /**
     * @return list<string> Paths relative to bundle root (sorted).
     */
    public function listBundleFiles(string $relativePath): array
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return [];
        }

        $disk = Storage::disk($this->disk());
        $prefix = $relativePath . '/';
        $files = [];

        foreach ($disk->allFiles($relativePath) as $absolute) {
            $absolute = str_replace('\\', '/', $absolute);
            if (!str_starts_with($absolute, $prefix)) {
                continue;
            }

            $files[] = substr($absolute, strlen($prefix));
        }

        sort($files);

        return $files;
    }

    private function assertZipUpload(UploadedFile $zip): void
    {
        if (!$zip->isValid()) {
            throw new RuntimeException('Invalid upload');
        }

        $max = (int) config('apphub.bundle_max_bytes', 52_428_800);
        if ($zip->getSize() > $max) {
            throw new RuntimeException('Bundle exceeds maximum size');
        }

        $ext = strtolower($zip->getClientOriginalExtension());
        if ($ext !== 'zip') {
            throw new RuntimeException('Bundle must be a .zip file');
        }
    }

    private function extractZipToTemp(UploadedFile $zip): string
    {
        $tempDir = sys_get_temp_dir() . '/apphub-unzip-' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Could not create temp directory');
        }

        $archive = new ZipArchive();
        if ($archive->open($zip->getRealPath()) !== true) {
            $this->deleteDirectory($tempDir);
            throw new RuntimeException('Could not open zip archive');
        }

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = (string) $archive->getNameIndex($i);
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                $archive->close();
                $this->deleteDirectory($tempDir);
                throw new RuntimeException('Unsafe path in zip archive');
            }
        }

        if (!$archive->extractTo($tempDir)) {
            $archive->close();
            $this->deleteDirectory($tempDir);
            throw new RuntimeException('Could not extract zip archive');
        }

        $archive->close();

        return $this->normalizeExtractRoot($tempDir);
    }

    private function normalizeExtractRoot(string $tempDir): string
    {
        $entries = array_values(array_filter(scandir($tempDir) ?: [], static fn ($e) => $e !== '.' && $e !== '..'));
        if (count($entries) === 1 && is_dir($tempDir . '/' . $entries[0])) {
            return $tempDir . '/' . $entries[0];
        }

        return $tempDir;
    }

    private function validateExtractedTree(string $root, string $entry): void
    {
        $entryPath = $root . '/' . ltrim($entry, '/');
        if (!is_file($entryPath)) {
            throw new RuntimeException('Bundle must contain ' . $entry);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/..') || str_contains($path, '../')) {
                throw new RuntimeException('Unsafe path in bundle');
            }

            if (!$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                throw new RuntimeException('Blocked file type in bundle: .' . $ext);
            }
        }
    }

    /**
     * @return array{path: string, hash: string, entry: string, file_count: int}
     */
    private function moveTreeToBundlePath(string $slug, string $sourceRoot, string $entry): array
    {
        $root = trim((string) config('apphub.bundle_storage_root', 'apphub/bundles'), '/');
        $relative = $root . '/' . $slug . '/' . now()->format('YmdHis');
        $disk = Storage::disk($this->disk());

        $fileCount = 0;
        $hashCtx = hash_init('sha256');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $local = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceRoot) + 1));
            if ($local === '') {
                continue;
            }

            if ($item->isDir()) {
                continue;
            }

            $contents = (string) file_get_contents($item->getPathname());
            hash_update($hashCtx, $local . "\0" . $contents);
            $disk->put($relative . '/' . $local, $contents);
            $fileCount++;
        }

        return [
            'path' => $relative,
            'hash' => hash_final($hashCtx),
            'entry' => $entry,
            'file_count' => $fileCount,
        ];
    }

    private function copyDirectory(string $source, string $dest): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $target = $dest . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0700, true);
                }
            } else {
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0700, true);
                }
                copy($item->getPathname(), $target);
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
