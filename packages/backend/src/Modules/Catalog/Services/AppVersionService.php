<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppVersion;
use Kennofizet\AppHub\Modules\Catalog\Support\AppVersionReviewStatus;

final class AppVersionService
{
    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppHubService $appHub,
    ) {
    }

    /**
     * Resolve bundle path + entry for launch/runtime (pinned version or catalog current).
     *
     * @return array{path: string, entry: string}|null
     */
    public function resolveBundle(App $app, ?string $version): ?array
    {
        $version = $version !== null ? trim($version) : '';

        if ($version === '') {
            $path = (string) ($app->bundle_path ?? '');
            if ($path === '') {
                return null;
            }

            return [
                'path' => $path,
                'entry' => ltrim((string) ($app->bundle_entry ?: 'index.html'), '/'),
            ];
        }

        $row = $this->findVersionRow($app, $version);
        if ($row === null || $row->bundle_path === null || $row->bundle_path === '') {
            return null;
        }

        return [
            'path' => (string) $row->bundle_path,
            'entry' => ltrim((string) ($row->bundle_entry ?: 'index.html'), '/'),
        ];
    }

    /**
     * Launch/runtime bundle resolution with DEV review gate for pinned versions.
     *
     * @return array{path: string, entry: string}|null
     */
    public function resolveLaunchBundle(App $app, ?string $version, int $userId): ?array
    {
        $versionKey = $version !== null ? trim($version) : '';
        $bundle = $this->resolveBundle($app, $versionKey !== '' ? $versionKey : null);
        if ($bundle === null) {
            return null;
        }

        if ($versionKey === '') {
            return $bundle;
        }

        if ($app->isActive() && $versionKey === (string) $app->version) {
            return $bundle;
        }

        $row = $this->findVersionRow($app, $versionKey);
        if ($row === null) {
            return null;
        }

        if (!$this->canUserLaunchVersion($app, $row, $userId)) {
            return null;
        }

        return $bundle;
    }

    /** @return list<string> */
    public function permissionsForLaunchBundle(App $app, ?string $version): array
    {
        $versionKey = $version !== null ? trim($version) : '';
        if ($versionKey === '') {
            return $this->permissionsForVersion($app, (string) $app->version);
        }

        return $this->permissionsForVersion($app, $versionKey);
    }

    /** @return list<string> */
    public function apiUrlsForLaunchBundle(App $app, ?string $version): array
    {
        $versionKey = $version !== null ? trim($version) : '';
        if ($versionKey === '') {
            return $this->apiUrlsForVersion($app, (string) $app->version);
        }

        return $this->apiUrlsForVersion($app, $versionKey);
    }

    /** @return list<string> */
    public function pinnedIpsForLaunchBundle(App $app, ?string $version): array
    {
        $versionKey = $version !== null ? trim($version) : '';
        if ($versionKey === '') {
            return $this->pinnedIpsForVersion($app, (string) $app->version);
        }

        return $this->pinnedIpsForVersion($app, $versionKey);
    }

    /** @return list<string> */
    private function pinnedIpsForVersion(App $app, string $version): array
    {
        $version = trim($version);
        if ($version !== '') {
            $row = $this->findVersionRow($app, $version);
            if ($row !== null) {
                return AppManifestApiUrl::pinnedIpsFromManifest(is_array($row->manifest) ? $row->manifest : null);
            }

            return [];
        }

        return AppManifestApiUrl::pinnedIpsFromManifest(is_array($app->manifest) ? $app->manifest : null);
    }

    /** @return list<string> */
    private function apiUrlsForVersion(App $app, string $version): array
    {
        $version = trim($version);
        if ($version !== '') {
            $row = $this->findVersionRow($app, $version);
            if ($row !== null) {
                return AppManifestApiUrl::fromManifest(is_array($row->manifest) ? $row->manifest : null);
            }

            return [];
        }

        return AppManifestApiUrl::fromManifest(is_array($app->manifest) ? $app->manifest : null);
    }

    /** @return list<string> */
    private function permissionsForVersion(App $app, string $version): array
    {
        $version = trim($version);
        if ($version !== '' && $version === (string) $app->version) {
            $fromApp = AppBridgeScope::fromManifest(is_array($app->manifest) ? $app->manifest : null);
            if ($fromApp !== []) {
                return $fromApp;
            }
        }

        $row = $version !== '' ? $this->findVersionRow($app, $version) : null;
        if ($row !== null && is_array($row->manifest)) {
            $fromRow = AppBridgeScope::fromManifest($row->manifest);
            if ($fromRow !== []) {
                return $fromRow;
            }
        }

        return AppBridgeScope::fromManifest(is_array($app->manifest) ? $app->manifest : null);
    }

    private function canUserLaunchVersion(App $app, AppVersion $row, int $userId): bool
    {
        $status = $this->resolveReviewStatus($app, $row);
        if ($status === AppVersionReviewStatus::PUBLISHED) {
            return true;
        }

        if ($status === AppVersionReviewStatus::SKIPPED) {
            return $this->appHub->isDevUser($userId);
        }

        return $this->catalog->userCanLaunchUnpublishedVersion($app, $userId);
    }

    private function findVersionRow(App $app, string $version): ?AppVersion
    {
        return AppVersion::query()
            ->where('app_id', $app->id)
            ->where('version', $version)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForApp(App $app, int $userId): array
    {
        if (!$this->canViewHistory($app, $userId)) {
            throw new \RuntimeException('You do not have permission to view version history');
        }

        return AppVersion::query()
            ->where('app_id', $app->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AppVersion $row): array => [
                'version' => $row->version,
                'review_status' => $this->resolveReviewStatus($app, $row),
                'bundle_hash' => $row->bundle_hash,
                'bundle_entry' => $row->bundle_entry,
                'file_count' => is_array($row->manifest) ? ($row->manifest['file_count'] ?? null) : null,
                'manifest' => $row->manifest,
                'uploaded_by_user_id' => $row->uploaded_by_user_id,
                'uploaded_at' => $row->created_at?->toIso8601String(),
                'is_current' => $row->version === $app->version,
            ])
            ->all();
    }

    private function resolveReviewStatus(App $app, AppVersion $row): string
    {
        $stored = (string) ($row->review_status ?? '');
        if ($stored === AppVersionReviewStatus::SKIPPED) {
            return AppVersionReviewStatus::SKIPPED;
        }

        if ($stored === AppVersionReviewStatus::REJECTED) {
            return AppVersionReviewStatus::REJECTED;
        }

        $version = (string) $row->version;
        $pending = trim((string) ($app->pending_version ?? ''));

        if ($pending !== '' && $version === $pending) {
            return AppVersionReviewStatus::PENDING;
        }

        if ($app->isDraft() && $version === (string) $app->version) {
            if ($stored === AppVersionReviewStatus::REJECTED) {
                return AppVersionReviewStatus::REJECTED;
            }

            return AppVersionReviewStatus::PENDING;
        }

        if ($stored === AppVersionReviewStatus::PUBLISHED) {
            return AppVersionReviewStatus::PUBLISHED;
        }

        if ($app->isActive() && $version === (string) $app->version) {
            return AppVersionReviewStatus::PUBLISHED;
        }

        if ($stored === AppVersionReviewStatus::PENDING) {
            return AppVersionReviewStatus::SKIPPED;
        }

        return AppVersionReviewStatus::PUBLISHED;
    }

    private function canViewHistory(App $app, int $userId): bool
    {
        if ($this->appHub->isDevUser($userId)) {
            return true;
        }

        if ((int) $app->owner_user_id === $userId) {
            return true;
        }

        return $this->catalog->userCanViewVersionHistory($app, $userId);
    }
}
