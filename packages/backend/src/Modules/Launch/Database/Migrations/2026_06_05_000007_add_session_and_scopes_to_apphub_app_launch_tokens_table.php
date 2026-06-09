<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.app_launch_tokens_table', 'apphub_app_launch_tokens');
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($table): void {
            if (!Schema::hasColumn($table, 'session_id')) {
                $blueprint->uuid('session_id')->nullable()->index();
            }
            if (!Schema::hasColumn($table, 'scopes_granted')) {
                $blueprint->json('scopes_granted')->nullable();
            }
        });
    }

    public function down(): void
    {
        $table = config('apphub.app_launch_tokens_table', 'apphub_app_launch_tokens');
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'scopes_granted')) {
                $blueprint->dropColumn('scopes_granted');
            }
            if (Schema::hasColumn($table, 'session_id')) {
                $blueprint->dropColumn('session_id');
            }
        });
    }
};
