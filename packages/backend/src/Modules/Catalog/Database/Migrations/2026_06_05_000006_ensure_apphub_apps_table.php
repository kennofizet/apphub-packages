<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;

/**
 * Ensures MVP apps table exists when host .env still points at legacy hub_apps
 * and the first migration was skipped because hub_apps already existed.
 */
return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.apps_table', 'apphub_apps');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('owner_user_id')->nullable()->index();
            $blueprint->string('slug', 64)->unique();
            $blueprint->string('name', 255);
            $blueprint->string('short_description', 500)->nullable();
            $blueprint->string('icon', 32)->nullable();
            $blueprint->string('status', 20)->default(AppStatus::DRAFT)->index();
            $blueprint->string('runtime_type', 20)->default(AppRuntimeType::IFRAME);
            $blueprint->string('entry_url', 2048)->nullable();
            $blueprint->string('healthcheck_url', 2048)->nullable();
            $blueprint->json('manifest')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();
            $blueprint->index(['status', 'id'], 'apphub_apps_status_id_idx');
        });
    }

    public function down(): void
    {
        // Intentionally no-op — table may pre-exist from 000001 on fresh installs.
    }
};
