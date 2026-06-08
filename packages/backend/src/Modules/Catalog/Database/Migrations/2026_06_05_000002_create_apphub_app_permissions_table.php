<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kennofizet\AppHub\Modules\Catalog\Support\AppPermissionType;

return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.app_permissions_table', 'apphub_app_permissions');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('app_id')->index();
            $blueprint->unsignedBigInteger('user_id')->index();
            $blueprint->string('permission', 32)->default(AppPermissionType::TEST);
            $blueprint->timestamps();
            $blueprint->unique(['app_id', 'user_id', 'permission'], 'apphub_app_permissions_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('apphub.app_permissions_table', 'apphub_app_permissions'));
    }
};
