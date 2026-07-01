<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.apps_table', 'apphub_apps');
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($table): void {
            if (!Schema::hasColumn($table, 'icon_asset_path')) {
                $blueprint->string('icon_asset_path', 512)->nullable()->after('icon');
            }
        });
    }

    public function down(): void
    {
        $table = config('apphub.apps_table', 'apphub_apps');
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'icon_asset_path')) {
                $blueprint->dropColumn('icon_asset_path');
            }
        });
    }
};
