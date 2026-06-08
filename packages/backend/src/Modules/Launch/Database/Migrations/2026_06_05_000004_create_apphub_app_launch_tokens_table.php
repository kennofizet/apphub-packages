<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.app_launch_tokens_table', 'apphub_app_launch_tokens');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('app_id')->index();
            $blueprint->unsignedBigInteger('user_id')->index();
            $blueprint->string('token_hash', 128)->unique();
            $blueprint->timestamp('expires_at')->index();
            $blueprint->timestamp('used_at')->nullable();
            $blueprint->string('ip', 45)->nullable();
            $blueprint->text('user_agent')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('apphub.app_launch_tokens_table', 'apphub_app_launch_tokens'));
    }
};
