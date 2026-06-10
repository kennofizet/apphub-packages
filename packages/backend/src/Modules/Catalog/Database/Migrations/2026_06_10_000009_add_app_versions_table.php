<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $appsTable = (string) config('apphub.apps_table', 'apphub_apps');
        $versionsTable = (string) config('apphub.app_versions_table', 'apphub_app_versions');

        if (Schema::hasTable($appsTable) && !Schema::hasColumn($appsTable, 'version')) {
            Schema::table($appsTable, function (Blueprint $blueprint): void {
                $blueprint->string('version', 64)->nullable()->after('slug');
            });
        }

        if (!Schema::hasTable($versionsTable)) {
            Schema::create($versionsTable, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->unsignedBigInteger('app_id');
                $blueprint->string('version', 64);
                $blueprint->string('bundle_path');
                $blueprint->string('bundle_hash', 64);
                $blueprint->string('bundle_entry', 255)->default('index.html');
                $blueprint->json('manifest')->nullable();
                $blueprint->unsignedBigInteger('uploaded_by_user_id');
                $blueprint->timestamps();

                $blueprint->unique(['app_id', 'version']);
                $blueprint->index(['app_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        $appsTable = (string) config('apphub.apps_table', 'apphub_apps');
        $versionsTable = (string) config('apphub.app_versions_table', 'apphub_app_versions');

        if (Schema::hasTable($versionsTable)) {
            Schema::dropIfExists($versionsTable);
        }

        if (Schema::hasTable($appsTable) && Schema::hasColumn($appsTable, 'version')) {
            Schema::table($appsTable, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('version');
            });
        }
    }
};
