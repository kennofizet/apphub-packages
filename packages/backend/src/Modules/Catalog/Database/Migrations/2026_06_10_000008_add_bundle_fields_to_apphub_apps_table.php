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
            if (!Schema::hasColumn($table, 'bundle_path')) {
                $blueprint->string('bundle_path', 512)->nullable()->after('manifest');
            }
            if (!Schema::hasColumn($table, 'bundle_hash')) {
                $blueprint->string('bundle_hash', 64)->nullable()->after('bundle_path');
            }
            if (!Schema::hasColumn($table, 'bundle_entry')) {
                $blueprint->string('bundle_entry', 255)->default('index.html')->after('bundle_hash');
            }
        });
    }

    public function down(): void
    {
        $table = config('apphub.apps_table', 'apphub_apps');
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['bundle_path', 'bundle_hash', 'bundle_entry']);
        });
    }
};
