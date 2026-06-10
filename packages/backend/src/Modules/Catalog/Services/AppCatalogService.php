<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Support\AppCatalogMode;
use Kennofizet\AppHub\Modules\Catalog\Support\AppPermissionType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;

final class AppCatalogService
{
    public function __construct(private readonly AppHubService $appHub)
    {
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

    public function userCanLaunch(App $app, int $userId, ?int $currentZoneId): bool
    {
        if ($app->isDisabled()) {
            return false;
        }

        if ($app->isDraft()) {
            return $this->userHasDraftAccess($app, $userId);
        }

        if ($app->isActive()) {
            return $currentZoneId !== null && $this->appAllowedInZone($app->id, $currentZoneId);
        }

        return false;
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

        /** @var App|null $last */
        $last = $apps->last();

        return [
            'items' => $apps->map(fn (App $app): array => $this->toCatalogItem($app))->all(),
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
            'items' => $this->mapPaginator($paginator),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function toCatalogItem(App $app): array
    {
        return [
            'slug' => $app->slug,
            'version' => $app->version,
            'name' => $app->name,
            'description' => $app->short_description,
            'icon' => $app->icon,
            'status' => $app->status,
            'runtime_type' => $app->runtime_type,
            'entry_url' => $app->entry_url,
            'healthcheck_url' => $app->healthcheck_url,
            'bundle_hash' => $app->bundle_hash,
            'bundle_entry' => $app->bundle_entry,
            'bundle_file_count' => is_array($app->manifest) ? ($app->manifest['file_count'] ?? null) : null,
            'installed' => false,
        ];
    }

    private function catalogQueryForMode(int $userId, ?int $currentZoneId, string $mode): Builder
    {
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

    private function appAllowedInZone(int $appId, int $zoneId): bool
    {
        return App::query()
            ->where('id', $appId)
            ->where('status', AppStatus::ACTIVE)
            ->whereHas('zoneAccess', static function ($zoneQuery) use ($zoneId): void {
                $zoneQuery->where('zone_id', $zoneId);
            })
            ->exists();
    }

    /** @param LengthAwarePaginator<int, App> $paginator */
    private function mapPaginator(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (App $app): array => $this->toCatalogItem($app))
            ->all();
    }
}
