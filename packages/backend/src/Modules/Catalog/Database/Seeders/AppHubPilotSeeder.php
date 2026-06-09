<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\AppPermission;
use Kennofizet\AppHub\Modules\Catalog\Models\AppZoneAccess;
use Kennofizet\AppHub\Modules\Catalog\Support\AppPermissionType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Kennofizet\PackagesCore\Models\User;
use Kennofizet\PackagesCore\Models\Zone;

class AppHubPilotSeeder extends Seeder
{
    public function run(): void
    {
        $ownerId = (int) (User::query()->orderBy('id')->value('id') ?? 1);
        $zoneId = (int) (Zone::query()->orderBy('id')->value('id') ?? 1);

        $draft = App::query()->updateOrCreate(
            ['slug' => 'pilot-draft'],
            [
                'owner_user_id' => $ownerId,
                'name' => 'Pilot Draft App',
                'short_description' => 'Draft app for publisher testing before approval.',
                'icon' => '🧪',
                'status' => AppStatus::DRAFT,
                'runtime_type' => AppRuntimeType::IFRAME,
                'entry_url' => 'https://tools.reg.local/apps/pilot-draft/',
                'healthcheck_url' => 'https://tools.reg.local/apps/pilot-draft/health',
                'manifest' => [
                    'slug' => 'pilot-draft',
                    'name' => 'Pilot Draft App',
                    'entry_url' => 'https://tools.reg.local/apps/pilot-draft/',
                ],
            ],
        );

        $active = App::query()->updateOrCreate(
            ['slug' => 'pilot-active'],
            [
                'owner_user_id' => $ownerId,
                'name' => 'Pilot Active App',
                'short_description' => 'Active app visible in App Store for allowed zones.',
                'icon' => '🚀',
                'status' => AppStatus::ACTIVE,
                'runtime_type' => AppRuntimeType::IFRAME,
                'entry_url' => 'https://tools.reg.local/apps/pilot-active/',
                'healthcheck_url' => 'https://tools.reg.local/apps/pilot-active/health',
                'manifest' => [
                    'slug' => 'pilot-active',
                    'name' => 'Pilot Active App',
                    'entry_url' => 'https://tools.reg.local/apps/pilot-active/',
                ],
            ],
        );

        AppPermission::query()->updateOrCreate(
            [
                'app_id' => $draft->id,
                'user_id' => $ownerId,
                'permission' => AppPermissionType::TEST,
            ],
            [],
        );

        AppPermission::query()->updateOrCreate(
            [
                'app_id' => $draft->id,
                'user_id' => $ownerId,
                'permission' => AppPermissionType::MANAGE,
            ],
            [],
        );

        AppZoneAccess::query()->updateOrCreate(
            [
                'app_id' => $active->id,
                'zone_id' => $zoneId,
            ],
            [],
        );
    }
}
