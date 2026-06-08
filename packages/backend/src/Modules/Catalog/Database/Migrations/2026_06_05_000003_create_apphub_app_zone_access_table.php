<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('apphub.app_zone_access_table', 'apphub_app_zone_access');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('app_id')->index();
            $blueprint->unsignedBigInteger('zone_id')->index();
            $blueprint->timestamps();
            $blueprint->unique(['app_id', 'zone_id'], 'apphub_app_zone_access_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('apphub.app_zone_access_table', 'apphub_app_zone_access'));
    }
};
