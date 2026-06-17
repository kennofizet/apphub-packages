<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.app_bridge_consent_intents_table', 'apphub_app_bridge_consent_intents');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('app_id')->index();
            $blueprint->unsignedBigInteger('user_id')->index();
            $blueprint->string('token_hash', 64)->unique();
            $blueprint->string('bundle_version', 64)->nullable();
            $blueprint->timestamp('expires_at');
            $blueprint->timestamp('used_at')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('apphub.app_bridge_consent_intents_table', 'apphub_app_bridge_consent_intents'));
    }
};
