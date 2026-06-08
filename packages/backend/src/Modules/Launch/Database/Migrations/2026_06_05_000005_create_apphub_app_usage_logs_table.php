<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.app_usage_logs_table', 'apphub_app_usage_logs');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('app_id')->index();
            $blueprint->unsignedBigInteger('user_id')->nullable()->index();
            $blueprint->string('action', 64)->index();
            $blueprint->json('metadata')->nullable();
            $blueprint->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('apphub.app_usage_logs_table', 'apphub_app_usage_logs'));
    }
};
