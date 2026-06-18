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
use Kennofizet\AppHub\Modules\Catalog\Support\AppVersionReviewStatus;
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

        $currentVersion = $this->resolveHighestVersion($existing);
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
        $nextVersion = AppSemver::normalize($meta['version']);

        $this->recordVersion($app, $ownerUserId, $nextVersion, $stored, $manifest);

        if ($app->status === AppStatus::ACTIVE) {
            return $this->queueActiveVersionUpgrade($app, $meta, $nextVersion);
        }

        $app->version = $nextVersion;
        $app->name = $meta['name'];
        $app->short_description = $meta['short_description'] ?? $app->short_description;
        $app->icon = $meta['icon'] ?? $app->icon;
        $app->healthcheck_url = $meta['healthcheck_url'] ?? $app->healthcheck_url;
        $app->manifest = $manifest;
        $app->bundle_path = $stored['path'];
        $app->bundle_hash = $stored['hash'];
        $app->bundle_entry = $stored['entry'];
        $app->pending_version = null;
        $app->status = AppStatus::DRAFT;
        $app->save();

        $this->skipOtherPendingVersions($app->id, $nextVersion);

        return $app;
    }

    /**
     * DEV review reject — pending upgrade on active app, or initial/resubmitted draft submission.
     */
    public function rejectDevReview(App $app): App
    {
        if ($app->isActive() && $app->hasPendingVersion()) {
            return $this->rejectPendingVersion($app);
        }

        if ($app->isDraft()) {
            return $this->rejectDraftSubmission($app);
        }

        throw new RuntimeException('Nothing to reject for this app');
    }

    /**
     * Reject a queued version upgrade on an already-active app (DEV review).
     */
    public function rejectPendingVersion(App $app): App
    {
        if (!$app->hasPendingVersion()) {
            throw new RuntimeException('No pending version to reject');
        }

        if (!$app->isActive()) {
            throw new RuntimeException('Only active apps with a pending version can be rejected this way');
        }

        $pending = AppSemver::normalize((string) ($app->pending_version ?? ''));
        $manifest = is_array($app->manifest) ? $app->manifest : [];
        $this->restoreCatalogFieldsFromManifest($app, $manifest);
        $app->pending_version = null;
        $app->save();

        if ($pending !== '') {
            $this->setVersionReviewStatus($app->id, $pending, AppVersionReviewStatus::REJECTED);
        }

        $this->skipStalePendingVersions($app->id);

        return $app;
    }

    /**
     * Reject an initial or resubmitted draft submission (app stays draft for publisher retry).
     */
    public function rejectDraftSubmission(App $app): App
    {
        if (!$app->isDraft()) {
            throw new RuntimeException('Only draft apps can be rejected this way');
        }

        if ($app->hasPendingVersion()) {
            throw new RuntimeException('Use reject pending for active app version upgrades');
        }

        $version = AppSemver::normalize((string) ($app->version ?? ''));
        if ($version === '' || !AppSemver::isValid($version)) {
            throw new RuntimeException('No version to reject');
        }

        $exists = AppVersion::query()
            ->where('app_id', $app->id)
            ->where('version', $version)
            ->exists();

        if (!$exists) {
            throw new RuntimeException('Version bundle not found');
        }

        $this->setVersionReviewStatus($app->id, $version, AppVersionReviewStatus::REJECTED);
        $this->skipOtherPendingVersions($app->id, $version);

        return $app->refresh() ?? $app;
    }

    /**
     * Promote a queued version on an already-active app (DEV approval).
     */
    public function promotePendingVersion(App $app): App
    {
        $pending = AppSemver::normalize((string) ($app->pending_version ?? ''));
        if ($pending === '') {
            throw new RuntimeException('No pending version to approve');
        }

        $row = AppVersion::query()
            ->where('app_id', $app->id)
            ->where('version', $pending)
            ->first();

        if ($row === null || $row->bundle_path === null || $row->bundle_path === '') {
            throw new RuntimeException('Pending version bundle not found');
        }

        $manifest = is_array($row->manifest) ? $row->manifest : [];

        $app->version = $pending;
        $app->manifest = $manifest;
        $app->bundle_path = (string) $row->bundle_path;
        $app->bundle_hash = (string) $row->bundle_hash;
        $app->bundle_entry = (string) ($row->bundle_entry ?: 'index.html');
        $app->pending_version = null;
        $app->status = AppStatus::ACTIVE;
        $app->save();

        $this->setVersionReviewStatus($app->id, $pending, AppVersionReviewStatus::PUBLISHED);
        $this->skipStalePendingVersions($app->id);

        return $app;
    }

    public function markLiveVersionPublished(App $app): void
    {
        $live = AppSemver::normalize((string) ($app->version ?? ''));
        if ($live === '') {
            return;
        }

        $this->setVersionReviewStatus($app->id, $live, AppVersionReviewStatus::PUBLISHED);
        $this->skipOtherPendingVersions($app->id, $live);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function queueActiveVersionUpgrade(App $app, array $meta, string $nextVersion): App
    {
        $app->name = $meta['name'];
        $app->short_description = $meta['short_description'] ?? $app->short_description;
        $app->icon = $meta['icon'] ?? $app->icon;
        $app->healthcheck_url = $meta['healthcheck_url'] ?? $app->healthcheck_url;
        $this->skipOtherPendingVersions($app->id, $nextVersion);
        $app->pending_version = $nextVersion;
        $app->save();

        return $app;
    }

    /**
     * Bundle to inspect during DEV review (pending upload or draft submission).
     *
     * @return array{
     *     path: string,
     *     entry: string,
     *     version: string,
     *     hash: string,
     *     manifest: array<string, mixed>|null,
     *     file_count: int|null
     * }|null
     */
    /**
     * Live / baseline bundle for diff when reviewing a version upgrade.
     *
     * @return array{path: string, entry: string, version: string}|null
     */
    public function resolveBaselineBundle(App $app): ?array
    {
        if (!$app->hasPendingVersion() || !$app->isActive()) {
            return null;
        }

        $path = (string) ($app->bundle_path ?? '');
        if ($path === '') {
            return null;
        }

        return [
            'path' => $path,
            'entry' => ltrim((string) ($app->bundle_entry ?: 'index.html'), '/'),
            'version' => (string) ($app->version ?? ''),
        ];
    }

    public function resolveReviewBundle(App $app): ?array
    {
        if ($app->hasPendingVersion()) {
            $row = AppVersion::query()
                ->where('app_id', $app->id)
                ->where('version', (string) $app->pending_version)
                ->first();

            if ($row !== null && $row->bundle_path !== null && $row->bundle_path !== '') {
                $manifest = is_array($row->manifest) ? $row->manifest : null;

                return [
                    'path' => (string) $row->bundle_path,
                    'entry' => ltrim((string) ($row->bundle_entry ?: 'index.html'), '/'),
                    'version' => (string) $row->version,
                    'hash' => (string) $row->bundle_hash,
                    'manifest' => $manifest,
                    'file_count' => is_array($manifest) ? ($manifest['file_count'] ?? null) : null,
                ];
            }
        }

        $path = (string) ($app->bundle_path ?? '');
        if ($path === '') {
            return null;
        }

        $manifest = is_array($app->manifest) ? $app->manifest : null;

        return [
            'path' => $path,
            'entry' => ltrim((string) ($app->bundle_entry ?: 'index.html'), '/'),
            'version' => (string) ($app->version ?? ''),
            'hash' => (string) ($app->bundle_hash ?? ''),
            'manifest' => $manifest,
            'file_count' => is_array($manifest) ? ($manifest['file_count'] ?? null) : null,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function restoreCatalogFieldsFromManifest(App $app, array $manifest): void
    {
        if (isset($manifest['name']) && is_string($manifest['name'])) {
            $app->name = $manifest['name'];
        }

        if (array_key_exists('description', $manifest)) {
            $description = $manifest['description'];
            $app->short_description = is_string($description) && $description !== ''
                ? $description
                : null;
        }

        if (isset($manifest['icon']) && is_string($manifest['icon']) && $manifest['icon'] !== '') {
            $app->icon = $manifest['icon'];
        }

        if (array_key_exists('healthcheck_url', $manifest)) {
            $url = $manifest['healthcheck_url'];
            $app->healthcheck_url = is_string($url) && $url !== '' ? $url : null;
        }
    }

    private function resolveHighestVersion(App $app): string
    {
        $live = (string) ($app->version ?? '0.0.0');
        $pending = trim((string) ($app->pending_version ?? ''));

        if ($pending !== '' && AppSemver::isGreaterThan($pending, $live)) {
            return $pending;
        }

        return $live;
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
            'review_status' => AppVersionReviewStatus::PENDING,
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

    private function setVersionReviewStatus(int $appId, string $version, string $status): void
    {
        if (!AppVersionReviewStatus::isValid($status)) {
            return;
        }

        AppVersion::query()
            ->where('app_id', $appId)
            ->where('version', AppSemver::normalize($version))
            ->update(['review_status' => $status]);
    }

    /** Mark every pending row except $keepVersion as skipped (newer upload superseded). */
    private function skipOtherPendingVersions(int $appId, ?string $keepVersion): void
    {
        $query = AppVersion::query()
            ->where('app_id', $appId)
            ->where('review_status', AppVersionReviewStatus::PENDING);

        $keep = $keepVersion !== null ? AppSemver::normalize($keepVersion) : '';
        if ($keep !== '') {
            $query->where('version', '!=', $keep);
        }

        $query->update(['review_status' => AppVersionReviewStatus::SKIPPED]);
    }

    /** Mark all remaining pending rows skipped (after approve/reject resolved the queue). */
    private function skipStalePendingVersions(int $appId): void
    {
        AppVersion::query()
            ->where('app_id', $appId)
            ->where('review_status', AppVersionReviewStatus::PENDING)
            ->update(['review_status' => AppVersionReviewStatus::SKIPPED]);
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
