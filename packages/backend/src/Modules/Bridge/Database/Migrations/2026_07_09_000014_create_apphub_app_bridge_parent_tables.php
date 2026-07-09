<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;

return new class extends Migration {
    public function up(): void
    {
        $parentConsentsTable = config(
            'apphub.app_bridge_parent_consents_table',
            'apphub_app_bridge_parent_consents',
        );
        $parentApprovalsTable = config(
            'apphub.app_bridge_parent_approvals_table',
            'apphub_app_bridge_parent_approvals',
        );
        $legacyConsentsTable = config('apphub.app_bridge_consents_table', 'apphub_app_bridge_consents');
        $appsTable = config('apphub.apps_table', 'apphub_apps');

        if (!Schema::hasTable($parentConsentsTable)) {
            Schema::create($parentConsentsTable, static function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->unsignedBigInteger('app_id')->index();
                $blueprint->unsignedBigInteger('user_id')->index();
                $blueprint->string('scope', 64);
                $blueprint->string('bundle_version', 64);
                $blueprint->timestamps();
                $blueprint->unique(
                    ['app_id', 'user_id', 'scope', 'bundle_version'],
                    'apphub_app_bridge_parent_consents_uq',
                );
            });
        }

        if (!Schema::hasTable($parentApprovalsTable)) {
            Schema::create($parentApprovalsTable, static function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->unsignedBigInteger('app_id')->index();
                $blueprint->string('bundle_version', 64);
                $blueprint->unsignedBigInteger('approved_by_user_id')->nullable();
                $blueprint->timestamp('approved_at')->nullable();
                $blueprint->timestamps();
                $blueprint->unique(['app_id', 'bundle_version'], 'apphub_app_bridge_parent_approvals_uq');
            });
        }

        if (!Schema::hasTable($legacyConsentsTable) || !Schema::hasTable($appsTable)) {
            return;
        }

        DB::table($legacyConsentsTable)
            ->where('scope', 'like', 'parent.%')
            ->delete();

        if (!Schema::hasTable($parentApprovalsTable)) {
            return;
        }

        $activeApps = DB::table($appsTable)
            ->where('status', AppStatus::ACTIVE)
            ->get(['id', 'version', 'owner_user_id']);

        $now = now();
        foreach ($activeApps as $app) {
            $version = AppSemver::normalize((string) ($app->version ?? ''));
            if ($version === '') {
                continue;
            }

            DB::table($parentApprovalsTable)->updateOrInsert(
                [
                    'app_id' => (int) $app->id,
                    'bundle_version' => $version,
                ],
                [
                    'approved_by_user_id' => (int) ($app->owner_user_id ?? 0) > 0
                        ? (int) $app->owner_user_id
                        : null,
                    'approved_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config(
            'apphub.app_bridge_parent_approvals_table',
            'apphub_app_bridge_parent_approvals',
        ));
        Schema::dropIfExists(config(
            'apphub.app_bridge_parent_consents_table',
            'apphub_app_bridge_parent_consents',
        ));
    }
};
