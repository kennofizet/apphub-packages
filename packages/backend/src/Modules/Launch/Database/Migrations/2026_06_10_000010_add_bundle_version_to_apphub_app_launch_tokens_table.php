<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('apphub.app_launch_tokens_table', 'apphub_app_launch_tokens');

        if (!Schema::hasTable($table) || Schema::hasColumn($table, 'bundle_version')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('bundle_version', 64)->nullable()->after('session_id');
        });
    }

    public function down(): void
    {
        $table = (string) config('apphub.app_launch_tokens_table', 'apphub_app_launch_tokens');

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'bundle_version')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('bundle_version');
        });
    }
};
