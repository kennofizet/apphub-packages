<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

use Illuminate\Database\Eloquent\Builder;
use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentService;
use Kennofizet\AppHub\Modules\Bridge\Support\AppBridgeScope;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Kennofizet\AppHub\Modules\Launch\Models\UserNotification;
use RuntimeException;

final class UserNotificationService
{
    public function __construct(
        private readonly AppBridgeConsentService $bridgeConsents,
    ) {
    }
    /**
     * Fan-out publisher notify to zone users who installed the app (desktop.notify consent).
     *
     * @return array{created: int, notification_ids: list<int>}
     */
    public function fanOutFromPublisher(
        App $app,
        string $title,
        string $body,
    ): array {
        if (!in_array((string) $app->status, [AppStatus::ACTIVE, AppStatus::DRAFT], true)) {
            throw new RuntimeException('App is not available for notifications');
        }

        $permissions = AppBridgeScope::fromManifest(is_array($app->manifest) ? $app->manifest : null);
        if (!in_array(AppBridgeScope::DESKTOP_NOTIFY, $permissions, true)) {
            throw new RuntimeException('App manifest does not declare desktop.notify');
        }

        $title = trim($title);
        if ($title === '') {
            throw new RuntimeException('Notification title is required');
        }

        $body = trim($body);
        $userIds = $this->bridgeConsents->notifyRecipientUserIdsForApp($app);
        if ($userIds->isEmpty()) {
            return ['created' => 0, 'notification_ids' => []];
        }

        $now = now();
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'app_id' => $app->id,
                'app_slug' => $app->slug,
                'app_name' => $app->name,
                'app_icon' => $app->icon,
                'title' => $title,
                'body' => $body !== '' ? $body : null,
                'read_at' => null,
                'dismissed_at' => null,
                'created_at' => $now,
            ];
        }

        UserNotification::query()->insert($rows);

        $ids = UserNotification::query()
            ->where('app_id', $app->id)
            ->where('title', $title)
            ->where('created_at', $now)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return ['created' => count($rows), 'notification_ids' => $ids];
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     meta: array{per_page: int, has_more: bool, next_cursor: string|null, unread_count: int}
     * }
     */
    public function cursorInboxForUser(int $userId, ?string $cursor, int $perPage): array
    {
        $perPage = max(1, min(50, $perPage));

        $query = UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('dismissed_at');

        if (!$this->applySubscribedAppsFilter($query, $userId)) {
            return [
                'items' => [],
                'meta' => [
                    'per_page' => $perPage,
                    'has_more' => false,
                    'next_cursor' => null,
                    'unread_count' => 0,
                ],
            ];
        }

        $query->orderByDesc('created_at')
            ->orderByDesc('id');

        $decoded = $this->decodeCursor($cursor);
        if ($decoded !== null) {
            $query->where(function (Builder $outer) use ($decoded): void {
                $outer->where('created_at', '<', $decoded['created_at'])
                    ->orWhere(function (Builder $inner) use ($decoded): void {
                        $inner->where('created_at', $decoded['created_at'])
                            ->where('id', '<', $decoded['id']);
                    });
            });
        }

        $rows = $query->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        if ($hasMore) {
            $rows = $rows->take($perPage);
        }

        /** @var UserNotification|null $last */
        $last = $rows->last();

        return [
            'items' => $rows->map(fn (UserNotification $row): array => $this->toItem($row))->all(),
            'meta' => [
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_cursor' => $hasMore && $last !== null ? $this->encodeCursor($last) : null,
                'unread_count' => $this->unreadCountForUser($userId),
            ],
        ];
    }

    public function unreadCountForUser(int $userId): int
    {
        $query = UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('dismissed_at')
            ->whereNull('read_at');

        if (!$this->applySubscribedAppsFilter($query, $userId)) {
            return 0;
        }

        return $query->count();
    }

    /** @param list<int|string> $ids */
    public function dismissForUser(int $userId, array $ids): int
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $ids,
        ), static fn (int $id): bool => $id > 0)));

        if ($normalized === []) {
            return 0;
        }

        $now = now();

        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('dismissed_at')
            ->whereIn('id', $normalized)
            ->update([
                'read_at' => $now,
                'dismissed_at' => $now,
            ]);
    }

    public function dismissAllForUser(int $userId): int
    {
        $now = now();

        $query = UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('dismissed_at');

        if (!$this->applySubscribedAppsFilter($query, $userId)) {
            return 0;
        }

        return $query->update([
            'read_at' => $now,
            'dismissed_at' => $now,
        ]);
    }

    public function dismissAllForUserAndApp(int $userId, int $appId): int
    {
        if ($userId < 1 || $appId < 1) {
            return 0;
        }

        $now = now();

        return UserNotification::query()
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->whereNull('dismissed_at')
            ->update([
                'read_at' => $now,
                'dismissed_at' => $now,
            ]);
    }

    /** @return array<string, mixed> */
    private function toItem(UserNotification $row): array
    {
        return [
            'id' => $row->id,
            'app_slug' => $row->app_slug,
            'app_name' => $row->app_name,
            'app_icon' => $row->app_icon,
            'title' => $row->title,
            'body' => $row->body,
            'read_at' => $row->read_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function applySubscribedAppsFilter(Builder $query, int $userId): bool
    {
        $appIds = $this->bridgeConsents->subscribedNotifyAppIdsForUser($userId);
        if ($appIds === []) {
            return false;
        }

        $query->whereIn('app_id', $appIds);

        return true;
    }

    private function encodeCursor(UserNotification $row): string
    {
        $payload = json_encode([
            'created_at' => $row->created_at?->format('Y-m-d H:i:s.u'),
            'id' => $row->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /** @return array{created_at: string, id: int}|null */
    private function decodeCursor(?string $cursor): ?array
    {
        if (!is_string($cursor) || trim($cursor) === '') {
            return null;
        }

        $pad = strlen($cursor) % 4;
        if ($pad > 0) {
            $cursor .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        try {
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || !isset($data['created_at'], $data['id'])) {
            return null;
        }

        return [
            'created_at' => (string) $data['created_at'],
            'id' => (int) $data['id'],
        ];
    }
}
