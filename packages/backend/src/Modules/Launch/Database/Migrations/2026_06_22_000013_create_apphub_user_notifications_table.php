<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('apphub.user_notifications_table', 'apphub_user_notifications');

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('user_id')->index();
            $blueprint->unsignedBigInteger('app_id')->index();
            $blueprint->string('app_slug', 64);
            $blueprint->string('app_name', 255)->nullable();
            $blueprint->string('app_icon', 16)->nullable();
            $blueprint->string('title', 255);
            $blueprint->text('body')->nullable();
            $blueprint->timestamp('read_at')->nullable();
            $blueprint->timestamp('dismissed_at')->nullable()->index();
            $blueprint->timestamp('created_at')->useCurrent();

            $blueprint->index(['user_id', 'dismissed_at', 'created_at', 'id'], 'apphub_user_notif_inbox_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('apphub.user_notifications_table', 'apphub_user_notifications'));
    }
};
