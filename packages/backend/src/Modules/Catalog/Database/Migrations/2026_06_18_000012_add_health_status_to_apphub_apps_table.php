<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kennofizet\AppHub\Modules\Catalog\Models\App;

return new class extends Migration
{
    public function up(): void
    {
        $table = (new App())->getTable();

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->boolean('health_ok')->nullable()->after('healthcheck_url');
            $blueprint->timestamp('health_checked_at')->nullable()->after('health_ok');
        });
    }

    public function down(): void
    {
        $table = (new App())->getTable();

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['health_ok', 'health_checked_at']);
        });
    }
};
