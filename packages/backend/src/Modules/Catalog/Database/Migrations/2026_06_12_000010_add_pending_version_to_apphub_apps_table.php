<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $appsTable = (string) config('apphub.apps_table', 'apphub_apps');

        if (Schema::hasTable($appsTable) && !Schema::hasColumn($appsTable, 'pending_version')) {
            Schema::table($appsTable, function (Blueprint $blueprint): void {
                $blueprint->string('pending_version', 64)->nullable()->after('version');
            });
        }
    }

    public function down(): void
    {
        $appsTable = (string) config('apphub.apps_table', 'apphub_apps');

        if (Schema::hasTable($appsTable) && Schema::hasColumn($appsTable, 'pending_version')) {
            Schema::table($appsTable, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('pending_version');
            });
        }
    }
};
