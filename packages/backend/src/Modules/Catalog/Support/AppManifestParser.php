<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Support;

use Illuminate\Http\UploadedFile;
use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use Kennofizet\AppHub\Modules\Launch\Services\AppEntryUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchDeniedException;
use RuntimeException;
use ZipArchive;

final class AppManifestParser
{
    /**
     * @return array{
     *     slug: string,
     *     name: string,
     *     version: string,
     *     short_description?: string|null,
     *     icon?: string|null,
     *     bundle_entry?: string|null,
     *     api_base_url?: string|null,
     *     healthcheck_url?: string|null,
     *     runtime_type: string,
     *     manifest: array<string, mixed>
     * }
     */
    public function fromZip(UploadedFile $zip): array
    {
        $archive = new ZipArchive();
        if ($archive->open($zip->getRealPath()) !== true) {
            throw new RuntimeException('Could not open zip archive');
        }

        try {
            $manifestPath = $this->findManifestPath($archive);
            if ($manifestPath === null) {
                throw new RuntimeException('Zip must contain manifest.json at the root (or one subfolder)');
            }

            $raw = $archive->getFromName($manifestPath);
            if ($raw === false || $raw === '') {
                throw new RuntimeException('Could not read manifest.json from zip');
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('manifest.json must be valid JSON');
            }

            return $this->normalizeHosted($decoded);
        } finally {
            $archive->close();
        }
    }

    /**
     * @return array{
     *     slug: string,
     *     name: string,
     *     version: string,
     *     short_description?: string|null,
     *     icon?: string|null,
     *     bundle_entry?: string|null,
     *     api_base_url?: string|null,
     *     healthcheck_url?: string|null,
     *     runtime_type: string,
     *     manifest: array<string, mixed>
     * }
     */
    public function fromJsonString(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('manifest.json must be valid JSON');
        }

        return $this->normalizeHosted($decoded);
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{
     *     slug: string,
     *     name: string,
     *     version: string,
     *     short_description?: string|null,
     *     icon?: string|null,
     *     entry_url: string,
     *     api_base_url?: string|null,
     *     healthcheck_url?: string|null,
     *     runtime_type: string,
     *     manifest: array<string, mixed>
     * }
     */
    public function normalizeIframe(array $manifest): array
    {
        $slug = strtolower(trim((string) ($manifest['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            throw new RuntimeException('manifest: slug is required (a-z0-9, max 64)');
        }

        $name = trim((string) ($manifest['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('manifest: name is required');
        }

        $version = AppSemver::normalize((string) ($manifest['version'] ?? ''));
        if ($version === '' || !AppSemver::isValid($version)) {
            throw new RuntimeException('manifest: version is required (semver, e.g. 1.0.0)');
        }

        $runtimeType = strtolower(trim((string) ($manifest['runtime_type'] ?? AppRuntimeType::IFRAME)));
        if ($runtimeType !== AppRuntimeType::IFRAME) {
            throw new RuntimeException('manifest: runtime_type must be "iframe" for entry_url register');
        }

        $entryUrl = trim((string) ($manifest['entry_url'] ?? $manifest['runtime_url'] ?? ''));
        if ($entryUrl === '') {
            throw new RuntimeException('manifest: entry_url is required for iframe apps');
        }

        try {
            AppEntryUrlGuard::assertRegisterableUrl($entryUrl);
        } catch (LaunchDeniedException $e) {
            throw new RuntimeException($e->getMessage());
        }

        $description = trim((string) ($manifest['description'] ?? $manifest['short_description'] ?? ''));

        $document = [
            'slug' => $slug,
            'name' => mb_substr($name, 0, 255),
            'version' => $version,
            'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
            'runtime_type' => AppRuntimeType::IFRAME,
            'entry_url' => mb_substr($entryUrl, 0, 2048),
            'icon' => $this->normalizeIcon($manifest['icon'] ?? null),
        ];

        foreach (['api_base_url', 'healthcheck_url'] as $urlKey) {
            if (!empty($manifest[$urlKey]) && is_string($manifest[$urlKey])) {
                $trimmed = mb_substr(trim($manifest[$urlKey]), 0, 2048);
                if ($urlKey === 'healthcheck_url') {
                    try {
                        AppHealthcheckUrlGuard::assertSafeUrl($trimmed);
                    } catch (LaunchDeniedException $e) {
                        throw new RuntimeException($e->getMessage());
                    }
                }
                $document[$urlKey] = $trimmed;
            }
        }

        $permissions = AppBridgeScope::normalizeList($manifest['permissions'] ?? null);
        if ($permissions !== []) {
            $document['permissions'] = $permissions;
        }

        $apiUrls = AppManifestApiUrl::fromManifest($manifest);
        if ($apiUrls !== []) {
            AppManifestApiUrl::assertProductionSafe($manifest);
            $document['api_urls'] = $apiUrls;
            $pinned = AppManifestApiUrl::resolvePinnedClientIps($apiUrls);
            if ($pinned !== []) {
                $document['api_url_pinned_ips'] = $pinned;
            }
        }

        return [
            'slug' => $slug,
            'name' => $document['name'],
            'version' => $version,
            'short_description' => $document['description'],
            'icon' => $document['icon'],
            'entry_url' => $document['entry_url'],
            'runtime_type' => AppRuntimeType::IFRAME,
            'api_base_url' => $document['api_base_url'] ?? null,
            'healthcheck_url' => $document['healthcheck_url'] ?? null,
            'manifest' => $document,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{
     *     slug: string,
     *     name: string,
     *     version: string,
     *     short_description?: string|null,
     *     icon?: string|null,
     *     bundle_entry?: string|null,
     *     api_base_url?: string|null,
     *     healthcheck_url?: string|null,
     *     runtime_type: string,
     *     manifest: array<string, mixed>
     * }
     */
    public function normalizeHosted(array $manifest): array
    {
        $slug = strtolower(trim((string) ($manifest['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            throw new RuntimeException('manifest.json: slug is required (a-z0-9, max 64)');
        }

        $name = trim((string) ($manifest['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('manifest.json: name is required');
        }

        $version = AppSemver::normalize((string) ($manifest['version'] ?? ''));
        if ($version === '' || !AppSemver::isValid($version)) {
            throw new RuntimeException('manifest.json: version is required (semver, e.g. 1.0.0)');
        }

        $runtimeType = strtolower(trim((string) ($manifest['runtime_type'] ?? AppRuntimeType::HOSTED)));
        if ($runtimeType !== AppRuntimeType::HOSTED) {
            throw new RuntimeException('manifest.json: runtime_type must be "hosted" for zip publish');
        }

        $main = trim((string) ($manifest['main'] ?? $manifest['bundle_entry'] ?? $manifest['entry'] ?? 'index.html'));
        if ($main === '') {
            $main = 'index.html';
        }

        $description = trim((string) ($manifest['description'] ?? $manifest['short_description'] ?? ''));

        $document = [
            'slug' => $slug,
            'name' => mb_substr($name, 0, 255),
            'version' => $version,
            'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
            'main' => mb_substr($main, 0, 255),
            'type' => $this->normalizeType($manifest['type'] ?? null),
            'keywords' => $this->normalizeKeywords($manifest['keywords'] ?? null),
            'author' => $this->normalizeAuthor($manifest['author'] ?? null),
            'license' => $this->normalizeLicense($manifest['license'] ?? null),
            'runtime_type' => AppRuntimeType::HOSTED,
            'icon' => $this->normalizeIcon($manifest['icon'] ?? null),
        ];

        foreach (['api_base_url', 'healthcheck_url'] as $urlKey) {
            if (!empty($manifest[$urlKey]) && is_string($manifest[$urlKey])) {
                $trimmed = mb_substr(trim($manifest[$urlKey]), 0, 2048);
                if ($urlKey === 'healthcheck_url') {
                    try {
                        AppHealthcheckUrlGuard::assertSafeUrl($trimmed);
                    } catch (LaunchDeniedException $e) {
                        throw new RuntimeException($e->getMessage());
                    }
                }
                $document[$urlKey] = $trimmed;
            }
        }

        $permissions = AppBridgeScope::normalizeList($manifest['permissions'] ?? null);
        if ($permissions !== []) {
            $document['permissions'] = $permissions;
        }

        $apiUrls = AppManifestApiUrl::fromManifest($manifest);
        if ($apiUrls !== []) {
            AppManifestApiUrl::assertProductionSafe($manifest);
            $document['api_urls'] = $apiUrls;
            $pinned = AppManifestApiUrl::resolvePinnedClientIps($apiUrls);
            if ($pinned !== []) {
                $document['api_url_pinned_ips'] = $pinned;
            }
        }

        return [
            'slug' => $slug,
            'name' => $document['name'],
            'version' => $version,
            'short_description' => $document['description'],
            'icon' => $document['icon'],
            'bundle_entry' => $document['main'],
            'runtime_type' => AppRuntimeType::HOSTED,
            'api_base_url' => $document['api_base_url'] ?? null,
            'healthcheck_url' => $document['healthcheck_url'] ?? null,
            'manifest' => $document,
        ];
    }

    private function findManifestPath(ZipArchive $archive): ?string
    {
        $candidates = [];

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = (string) $archive->getNameIndex($i);
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            if (preg_match('#(^|/)manifest\.json$#i', str_replace('\\', '/', $name))) {
                $candidates[] = str_replace('\\', '/', $name);
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (string $a, string $b): int {
            $depthA = substr_count($a, '/');
            $depthB = substr_count($b, '/');
            if ($depthA !== $depthB) {
                return $depthA <=> $depthB;
            }

            return strcmp($a, $b);
        });

        return $candidates[0];
    }

    private function normalizeIcon(mixed $icon): ?string
    {
        if ($icon === null || $icon === '') {
            return '📦';
        }

        $value = trim((string) $icon);
        if ($value === '') {
            return '📦';
        }

        if (self::iconBundleRelative($value) !== null || str_starts_with($value, 'data:image/')) {
            return '📦';
        }

        return mb_substr($value, 0, 32);
    }

    public static function iconBundleRelative(mixed $icon): ?string
    {
        if (!is_string($icon) || trim($icon) === '') {
            return null;
        }

        $value = ltrim(str_replace('\\', '/', trim($icon)), './');
        if ($value === '' || str_contains($value, '..')) {
            return null;
        }

        if (preg_match('#^https?://#i', $value)) {
            return null;
        }

        if (!preg_match('#\.(png|svg|jpe?g|webp)$#i', $value)) {
            return null;
        }

        return $value;
    }

    public static function isRemoteIconUrl(mixed $icon): bool
    {
        return is_string($icon) && preg_match('#^https?://#i', trim($icon)) === 1;
    }

    private function normalizeType(mixed $type): ?string
    {
        if ($type === null || $type === '') {
            return 'module';
        }

        $value = strtolower(trim((string) $type));

        return $value !== '' ? mb_substr($value, 0, 32) : 'module';
    }

    /**
     * @return list<string>
     */
    private function normalizeKeywords(mixed $keywords): array
    {
        if (!is_array($keywords)) {
            return [];
        }

        $out = [];
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }
            $value = trim($keyword);
            if ($value === '') {
                continue;
            }
            $out[] = mb_substr($value, 0, 64);
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    private function normalizeAuthor(mixed $author): string|array|null
    {
        if ($author === null || $author === '') {
            return null;
        }

        if (is_string($author)) {
            $value = trim($author);

            return $value !== '' ? mb_substr($value, 0, 255) : null;
        }

        if (!is_array($author)) {
            return null;
        }

        $name = trim((string) ($author['name'] ?? ''));
        $email = trim((string) ($author['email'] ?? ''));
        if ($name === '' && $email === '') {
            return null;
        }

        return array_filter([
            'name' => $name !== '' ? mb_substr($name, 0, 255) : null,
            'email' => $email !== '' ? mb_substr($email, 0, 255) : null,
        ]);
    }

    private function normalizeLicense(mixed $license): ?string
    {
        if ($license === null || $license === '') {
            return null;
        }

        $value = trim((string) $license);

        return $value !== '' ? mb_substr($value, 0, 64) : null;
    }
}
