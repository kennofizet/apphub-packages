<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Http\UploadedFile;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppPermission;
use Kennofizet\AppHub\Modules\Catalog\Models\AppVersion;
use Kennofizet\AppHub\Modules\Catalog\Support\AppPermissionType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use RuntimeException;

final class AppPublishService
{
    public function __construct(private readonly AppBundleStorageService $bundles)
    {
    }

    /**
     * Register new hosted app or upload a newer version (owner only).
     *
     * @param array{
     *     slug: string,
     *     name: string,
     *     version: string,
     *     short_description?: string|null,
     *     icon?: string|null,
     *     bundle_entry?: string|null,
     *     api_base_url?: string|null,
     *     healthcheck_url?: string|null,
     *     manifest: array<string, mixed>
     * } $meta
     */
    public function registerHosted(int $ownerUserId, array $meta, UploadedFile $zip): App
    {
        $slug = $this->normalizeSlug($meta['slug']);
        $existing = App::query()->where('slug', $slug)->first();

        if ($existing === null) {
            return $this->createHostedApp($ownerUserId, $meta, $zip);
        }

        if ((int) $existing->owner_user_id !== $ownerUserId) {
            throw new RuntimeException('App slug already exists');
        }

        $currentVersion = (string) ($existing->version ?? '0.0.0');
        $nextVersion = AppSemver::normalize($meta['version']);

        if (!AppSemver::isGreaterThan($nextVersion, $currentVersion)) {
            throw new RuntimeException('Version must be greater than ' . $currentVersion);
        }

        if (AppVersion::query()->where('app_id', $existing->id)->where('version', $nextVersion)->exists()) {
            throw new RuntimeException('Version ' . $nextVersion . ' was already uploaded');
        }

        return $this->upgradeHostedApp($existing, $ownerUserId, $meta, $zip);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function createHostedApp(int $ownerUserId, array $meta, UploadedFile $zip): App
    {
        $slug = $this->normalizeSlug($meta['slug']);
        $entry = $this->resolveEntry($meta);
        $stored = $this->bundles->storeFromZip($slug, $zip, $entry);
        $manifest = $this->buildStoredManifest($meta, $stored);

        $app = App::query()->create([
            'owner_user_id' => $ownerUserId,
            'slug' => $slug,
            'version' => $meta['version'],
            'name' => $meta['name'],
            'short_description' => $meta['short_description'] ?? null,
            'icon' => $meta['icon'] ?? '📦',
            'status' => AppStatus::DRAFT,
            'runtime_type' => AppRuntimeType::HOSTED,
            'entry_url' => null,
            'healthcheck_url' => $meta['healthcheck_url'] ?? null,
            'manifest' => $manifest,
            'bundle_path' => $stored['path'],
            'bundle_hash' => $stored['hash'],
            'bundle_entry' => $stored['entry'],
        ]);

        $this->recordVersion($app, $ownerUserId, $meta['version'], $stored, $manifest);
        $this->ensureOwnerPermissions($app, $ownerUserId);

        return $app;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function upgradeHostedApp(App $app, int $ownerUserId, array $meta, UploadedFile $zip): App
    {
        if ($app->runtime_type !== AppRuntimeType::HOSTED) {
            throw new RuntimeException('App is not a hosted runtime app');
        }

        $entry = $this->resolveEntry($meta);
        $stored = $this->bundles->storeFromZip($app->slug, $zip, $entry);
        $manifest = $this->buildStoredManifest($meta, $stored);

        $app->version = $meta['version'];
        $app->name = $meta['name'];
        $app->short_description = $meta['short_description'] ?? $app->short_description;
        $app->icon = $meta['icon'] ?? $app->icon;
        $app->healthcheck_url = $meta['healthcheck_url'] ?? $app->healthcheck_url;
        $app->manifest = $manifest;
        $app->bundle_path = $stored['path'];
        $app->bundle_hash = $stored['hash'];
        $app->bundle_entry = $stored['entry'];
        $app->status = AppStatus::DRAFT;
        $app->save();

        $this->recordVersion($app, $ownerUserId, $meta['version'], $stored, $manifest);

        return $app;
    }

    /**
     * @param array{path: string, hash: string, entry: string, file_count: int} $stored
     * @param array<string, mixed> $manifest
     */
    private function recordVersion(App $app, int $userId, string $version, array $stored, array $manifest): void
    {
        AppVersion::query()->create([
            'app_id' => $app->id,
            'version' => $version,
            'bundle_path' => $stored['path'],
            'bundle_hash' => $stored['hash'],
            'bundle_entry' => $stored['entry'],
            'manifest' => $manifest,
            'uploaded_by_user_id' => $userId,
        ]);
    }

    /**
     * @param array{path: string, hash: string, entry: string, file_count: int} $stored
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function buildStoredManifest(array $meta, array $stored): array
    {
        $document = is_array($meta['manifest'] ?? null) ? $meta['manifest'] : [];
        $document['bundle_hash'] = $stored['hash'];
        $document['file_count'] = $stored['file_count'];
        $document['bundle_entry'] = $stored['entry'];

        return $document;
    }

    private function ensureOwnerPermissions(App $app, int $ownerUserId): void
    {
        AppPermission::query()->updateOrCreate(
            [
                'app_id' => $app->id,
                'user_id' => $ownerUserId,
                'permission' => AppPermissionType::MANAGE,
            ],
            [],
        );

        AppPermission::query()->updateOrCreate(
            [
                'app_id' => $app->id,
                'user_id' => $ownerUserId,
                'permission' => AppPermissionType::TEST,
            ],
            [],
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function resolveEntry(array $meta): string
    {
        $entry = isset($meta['bundle_entry']) ? trim((string) $meta['bundle_entry']) : 'index.html';

        return $entry !== '' ? $entry : 'index.html';
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            throw new RuntimeException('Invalid app slug');
        }

        return $slug;
    }
}
