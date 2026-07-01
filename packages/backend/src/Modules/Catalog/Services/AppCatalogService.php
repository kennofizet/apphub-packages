<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppVersion;
use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;
use Kennofizet\AppHub\Modules\Catalog\Support\AppVersionReviewStatus;
use Kennofizet\AppHub\Modules\Catalog\Support\AppPermissionType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckService;

final class AppCatalogService
{
    public function __construct(
        private readonly AppHubService $appHub,
        private readonly AppHealthcheckService $healthcheck,
    ) {
    }

    public function findBySlug(string $slug): ?App
    {
        return App::query()->where('slug', $slug)->first();
    }

    public function userCanViewVersionHistory(App $app, int $userId): bool
    {
        if ($this->appHub->isDevUser($userId)) {
            return true;
        }

        if ((int) $app->owner_user_id === $userId) {
            return true;
        }

        return App::query()
            ->where('id', $app->id)
            ->whereHas('permissions', static function ($permQuery) use ($userId): void {
                $permQuery->where('user_id', $userId)
                    ->where('permission', AppPermissionType::MANAGE);
            })
            ->exists();
    }

    public function userCanLaunch(App $app, int $userId, array $userZoneIds): bool
    {
        if ($app->isDisabled()) {
            return false;
        }

        if ($app->isDraft()) {
            return $this->userHasDraftAccess($app, $userId);
        }

        if ($app->isActive()) {
            $zoneIds = self::normalizeUserZoneIds($userZoneIds);
            if ($zoneIds === []) {
                return false;
            }

            return $this->appAllowedInAnyZone($app->id, $zoneIds);
        }

        return false;
    }

    /** Owner, dev, draft testers, or manage permission — may launch pending/rejected bundles. */
    public function userCanLaunchUnpublishedVersion(App $app, int $userId): bool
    {
        if ($this->appHub->isDevUser($userId)) {
            return true;
        }

        if ((int) $app->owner_user_id === $userId) {
            return true;
        }

        if ($app->isDraft()) {
            return $this->userHasDraftAccess($app, $userId);
        }

        return $this->userCanViewVersionHistory($app, $userId);
    }

    /**
     * Cursor-paginated catalog for a single mode (store = active zone apps, draft = test apps).
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     meta: array{mode: string, per_page: int, has_more: bool, next_cursor: string|null}
     * }
     */
    public function cursorPaginateForUser(
        int $userId,
        ?int $currentZoneId,
        string $mode,
        ?string $cursor,
        int $perPage,
    ): array {
        $mode = AppCatalogMode::normalize($mode);
        $perPage = max(1, min(50, $perPage));

        $query = $this->catalogQueryForMode($userId, $currentZoneId, $mode)
            ->orderBy('name')
            ->orderBy('id');

        $decoded = $this->decodeCursor($cursor);
        if ($decoded !== null) {
            $query->where(function (Builder $outer) use ($decoded): void {
                $outer->where('name', '>', $decoded['name'])
                    ->orWhere(function (Builder $inner) use ($decoded): void {
                        $inner->where('name', $decoded['name'])
                            ->where('id', '>', $decoded['id']);
                    });
            });
        }

        $apps = $query->limit($perPage + 1)->get();
        $hasMore = $apps->count() > $perPage;
        if ($hasMore) {
            $apps = $apps->take($perPage);
        }

        if ($mode === AppCatalogMode::STORE && $apps->isNotEmpty()) {
            $this->healthcheck->refreshStaleApps($apps);
        }

        /** @var App|null $last */
        $last = $apps->last();

        return [
            'items' => $apps->map(fn (App $app): array => $this->toCatalogItem($app, $userId, $mode))->all(),
            'meta' => [
                'mode' => $mode,
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_cursor' => $hasMore && $last !== null ? $this->encodeCursor($last) : null,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, ?int $currentZoneId, string $mode, int $limit = 24): array
    {
        return $this->cursorPaginateForUser(
            $userId,
            $currentZoneId,
            $mode,
            null,
            $limit,
        )['items'];
    }

    /** @return array{items: list<array<string, mixed>>, meta: array<string, int|null>} */
    public function paginateForDev(int $page, int $perPage): array
    {
        $paginator = App::query()
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())
                ->map(fn (App $app): array => $this->toCatalogItem($app, null, 'dev'))
                ->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function appAwaitingDevReview(App $app): bool
    {
        if ($app->hasPendingVersion()) {
            return true;
        }

        if (!$app->isDraft()) {
            return false;
        }

        $version = AppSemver::normalize(trim((string) ($app->version ?? '')));
        if ($version === '') {
            return false;
        }

        $stored = $this->storedVersionReviewStatus($app, $version);

        if ($stored === AppVersionReviewStatus::REJECTED) {
            return false;
        }

        return $stored === AppVersionReviewStatus::PENDING || $stored === null;
    }

    private function storedVersionReviewStatus(App $app, string $version): ?string
    {
        $normalized = AppSemver::normalize($version);
        if ($normalized === '') {
            return null;
        }

        $row = AppVersion::query()
            ->where('app_id', $app->id)
            ->where('version', $normalized)
            ->first();

        if ($row === null) {
            return null;
        }

        $stored = trim((string) ($row->review_status ?? ''));

        return $stored !== '' ? $stored : null;
    }

    private function currentVersionReviewStatus(App $app): ?string
    {
        $version = AppSemver::normalize(trim((string) ($app->version ?? '')));
        if ($version === '') {
            return null;
        }

        return $this->storedVersionReviewStatus($app, $version);
    }

    /** @return array<string, mixed> */
    public function toCatalogItem(App $app, ?int $viewerUserId = null, ?string $catalogMode = null): array
    {
        $showReviewFields = $this->canViewPublisherReviewFields($app, $viewerUserId, $catalogMode);

        return [
            'slug' => $app->slug,
            'version' => $app->version,
            'pending_version' => $showReviewFields ? $app->pending_version : null,
            'rejected_version' => $showReviewFields ? $this->publisherRejectedVersion($app) : null,
            'awaiting_dev_review' => $showReviewFields ? $this->appAwaitingDevReview($app) : null,
            'current_version_review_status' => $showReviewFields ? $this->currentVersionReviewStatus($app) : null,
            'name' => $app->name,
            'description' => $app->short_description,
            'icon' => $this->catalogIconLabel($app),
            'icon_url' => $this->catalogIconUrl($app),
            'status' => $app->status,
            'runtime_type' => $app->runtime_type,
            'entry_url' => $app->entry_url,
            'healthcheck_url' => $showReviewFields ? $app->healthcheck_url : null,
            'health_ok' => $showReviewFields && $app->healthcheck_url ? $app->health_ok : null,
            'health_checked_at' => $showReviewFields && $app->healthcheck_url
                ? $app->health_checked_at?->toIso8601String()
                : null,
            'bundle_hash' => $app->bundle_hash,
            'bundle_entry' => $app->bundle_entry,
            'bundle_file_count' => is_array($app->manifest) ? ($app->manifest['file_count'] ?? null) : null,
            'permissions' => $this->resolvePermissionsForCatalog($app),
            'api_urls' => $this->resolveApiUrlsForCatalog($app),
            'installed' => false,
        ];
    }

    /** @return list<string> */
    private function resolvePermissionsForCatalog(App $app): array
    {
        $fromApp = AppBridgeScope::fromManifest(is_array($app->manifest) ? $app->manifest : null);
        if ($fromApp !== []) {
            return $fromApp;
        }

        $liveVersion = trim((string) ($app->version ?? ''));
        if ($liveVersion !== '') {
            $fromLive = $this->permissionsFromVersion($app->id, $liveVersion);
            if ($fromLive !== []) {
                return $fromLive;
            }
        }

        $pending = trim((string) ($app->pending_version ?? ''));
        if ($pending !== '') {
            return $this->permissionsFromVersion($app->id, $pending);
        }

        return [];
    }

    /** @return list<string> */
    private function resolveApiUrlsForCatalog(App $app): array
    {
        $fromApp = AppManifestApiUrl::fromManifest(is_array($app->manifest) ? $app->manifest : null);
        if ($fromApp !== []) {
            return $fromApp;
        }

        $liveVersion = trim((string) ($app->version ?? ''));
        if ($liveVersion !== '') {
            $fromLive = $this->apiUrlsFromVersion($app->id, $liveVersion);
            if ($fromLive !== []) {
                return $fromLive;
            }
        }

        $pending = trim((string) ($app->pending_version ?? ''));
        if ($pending !== '') {
            return $this->apiUrlsFromVersion($app->id, $pending);
        }

        return [];
    }

    /** @return list<string> */
    private function apiUrlsFromVersion(int $appId, string $version): array
    {
        $row = AppVersion::query()
            ->where('app_id', $appId)
            ->where('version', $version)
            ->first();

        if ($row === null || !is_array($row->manifest)) {
            return [];
        }

        return AppManifestApiUrl::fromManifest($row->manifest);
    }

    /** @return list<string> */
    private function permissionsFromVersion(int $appId, string $version): array
    {
        $row = AppVersion::query()
            ->where('app_id', $appId)
            ->where('version', $version)
            ->first();

        if ($row === null || !is_array($row->manifest)) {
            return [];
        }

        return AppBridgeScope::fromManifest($row->manifest);
    }

    private function canViewPublisherReviewFields(App $app, ?int $viewerUserId, ?string $catalogMode): bool
    {
        $mode = AppCatalogMode::normalize($catalogMode ?? '');
        if ($mode === AppCatalogMode::PUBLISHER || $mode === AppCatalogMode::DRAFT) {
            return true;
        }

        if ($catalogMode === 'dev') {
            return true;
        }

        if ($viewerUserId === null || $viewerUserId < 1) {
            return false;
        }

        if ($this->appHub->isDevUser($viewerUserId)) {
            return true;
        }

        if ((int) $app->owner_user_id === $viewerUserId) {
            return true;
        }

        return $this->userCanViewVersionHistory($app, $viewerUserId);
    }

    private function publisherRejectedVersion(App $app): ?string
    {
        $live = trim((string) ($app->version ?? ''));
        $pending = trim((string) ($app->pending_version ?? ''));

        if ($app->isDraft() && $live !== '') {
            $stored = $this->storedVersionReviewStatus($app, $live);

            return $stored === AppVersionReviewStatus::REJECTED
                ? AppSemver::normalize($live)
                : null;
        }

        if (!$app->isActive()) {
            return null;
        }

        $query = AppVersion::query()
            ->where('app_id', $app->id)
            ->where('review_status', AppVersionReviewStatus::REJECTED);

        if ($live !== '') {
            $query->where('version', '!=', $live);
        }

        $row = $query->orderByDesc('created_at')->first();
        if ($row === null) {
            return null;
        }

        $version = trim((string) $row->version);
        if ($version === '' || ($pending !== '' && $version === $pending)) {
            return null;
        }

        return $version;
    }

    private function catalogQueryForMode(int $userId, ?int $currentZoneId, string $mode): Builder
    {
        if ($mode === AppCatalogMode::PUBLISHER) {
            return App::query()
                ->where('owner_user_id', $userId)
                ->whereIn('status', [AppStatus::DRAFT, AppStatus::ACTIVE]);
        }

        if ($mode === AppCatalogMode::DRAFT) {
            return App::query()
                ->where('status', AppStatus::DRAFT)
                ->where(function (Builder $draftAccess) use ($userId): void {
                    $draftAccess->where('owner_user_id', $userId)
                        ->orWhereHas('permissions', static function ($permQuery) use ($userId): void {
                            $permQuery->where('user_id', $userId)
                                ->whereIn('permission', AppPermissionType::ALL);
                        });
                });
        }

        return App::query()
            ->where('status', AppStatus::ACTIVE)
            ->when(
                $currentZoneId !== null,
                static function (Builder $active) use ($currentZoneId): void {
                    $active->whereHas('zoneAccess', static function ($zoneQuery) use ($currentZoneId): void {
                        $zoneQuery->where('zone_id', $currentZoneId);
                    });
                },
                static function (Builder $active): void {
                    $active->whereRaw('0 = 1');
                },
            );
    }

    /** @return array{name: string, id: int}|null */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }

        try {
            /** @var array{name?: string, id?: int|string} $payload */
            $payload = json_decode($raw, true, 2, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($name === '' || $id < 1) {
            return null;
        }

        return ['name' => $name, 'id' => $id];
    }

    private function encodeCursor(App $app): string
    {
        $json = json_encode([
            'name' => (string) $app->name,
            'id' => (int) $app->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Draft: owner, explicit test/manage permission, or App Hub dev.
     * Disabled apps are never launchable.
     */
    private function userHasDraftAccess(App $app, int $userId): bool
    {
        if ($this->appHub->isDevUser($userId)) {
            return true;
        }

        if ((int) $app->owner_user_id === $userId) {
            return true;
        }

        return App::query()
            ->where('id', $app->id)
            ->where('status', AppStatus::DRAFT)
            ->whereHas('permissions', static function ($permQuery) use ($userId): void {
                $permQuery->where('user_id', $userId)
                    ->whereIn('permission', AppPermissionType::ALL);
            })
            ->exists();
    }

    /**
     * @param list<int> $zoneIds
     */
    private function appAllowedInAnyZone(int $appId, array $zoneIds): bool
    {
        $zoneIds = self::normalizeUserZoneIds($zoneIds);
        if ($zoneIds === []) {
            return false;
        }

        return App::query()
            ->where('id', $appId)
            ->where('status', AppStatus::ACTIVE)
            ->whereHas('zoneAccess', static function ($zoneQuery) use ($zoneIds): void {
                $zoneQuery->whereIn('zone_id', $zoneIds);
            })
            ->exists();
    }

    /**
     * @param array<int|string|null> $raw
     * @return list<int>
     */
    public static function normalizeUserZoneIds(array $raw): array
    {
        $ids = [];
        foreach ($raw as $zoneId) {
            $id = (int) $zoneId;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @param LengthAwarePaginator<int, App> $paginator */
    private function mapPaginator(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (App $app): array => $this->toCatalogItem($app))
            ->all();
    }

    private function catalogIconLabel(App $app): string
    {
        $label = trim((string) ($app->icon ?? ''));
        if ($label !== '' && !$this->looksLikeImageFilename($label)) {
            return $label;
        }

        return '📦';
    }

    private function catalogIconUrl(App $app): ?string
    {
        if (trim((string) ($app->icon_asset_path ?? '')) !== '') {
            return 'apps/' . $app->slug . '/icon';
        }

        $manifest = is_array($app->manifest) ? $app->manifest : [];
        $manifestIcon = $manifest['icon'] ?? null;
        if (is_string($manifestIcon) && preg_match('#^https?://#i', trim($manifestIcon))) {
            return trim($manifestIcon);
        }

        return null;
    }

    private function looksLikeImageFilename(string $value): bool
    {
        return preg_match('#\.(png|svg|jpe?g|webp)$#i', $value) === 1;
    }
}
