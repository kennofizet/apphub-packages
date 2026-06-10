<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Services;

use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppVersion;

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

        $row = AppVersion::query()
            ->where('app_id', $app->id)
            ->where('version', $version)
            ->first();

        if ($row === null || $row->bundle_path === null || $row->bundle_path === '') {
            return null;
        }

        return [
            'path' => (string) $row->bundle_path,
            'entry' => ltrim((string) ($row->bundle_entry ?: 'index.html'), '/'),
        ];
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
            ->map(static fn (AppVersion $row): array => [
                'version' => $row->version,
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
