<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Kennofizet\AppHub\Modules\Catalog\Support\AppVersionReviewStatus;

return new class extends Migration
{
    public function up(): void
    {
        $appsTable = (string) config('apphub.apps_table', 'apphub_apps');
        $versionsTable = (string) config('apphub.app_versions_table', 'apphub_app_versions');

        if (!Schema::hasTable($versionsTable) || Schema::hasColumn($versionsTable, 'review_status')) {
            return;
        }

        Schema::table($versionsTable, function (Blueprint $blueprint): void {
            $blueprint->string('review_status', 20)
                ->default(AppVersionReviewStatus::PENDING)
                ->after('version');
        });

        if (!Schema::hasTable($appsTable)) {
            return;
        }

        DB::table($versionsTable)
            ->update(['review_status' => AppVersionReviewStatus::PUBLISHED]);

        $apps = DB::table($appsTable)->select(['id', 'version', 'pending_version', 'status'])->get();

        foreach ($apps as $app) {
            $appId = (int) $app->id;
            $liveVersion = trim((string) ($app->version ?? ''));
            $pendingVersion = trim((string) ($app->pending_version ?? ''));
            $status = (string) ($app->status ?? '');

            if ($liveVersion !== '' && $status === AppStatus::ACTIVE) {
                DB::table($versionsTable)
                    ->where('app_id', $appId)
                    ->where('version', $liveVersion)
                    ->update(['review_status' => AppVersionReviewStatus::PUBLISHED]);
            }

            if ($pendingVersion !== '') {
                DB::table($versionsTable)
                    ->where('app_id', $appId)
                    ->where('version', $pendingVersion)
                    ->update(['review_status' => AppVersionReviewStatus::PENDING]);
            }

            if ($status === AppStatus::DRAFT && $liveVersion !== '') {
                DB::table($versionsTable)
                    ->where('app_id', $appId)
                    ->where('version', $liveVersion)
                    ->update(['review_status' => AppVersionReviewStatus::PENDING]);
            }
        }
    }

    public function down(): void
    {
        $versionsTable = (string) config('apphub.app_versions_table', 'apphub_app_versions');

        if (Schema::hasTable($versionsTable) && Schema::hasColumn($versionsTable, 'review_status')) {
            Schema::table($versionsTable, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('review_status');
            });
        }
    }
};
