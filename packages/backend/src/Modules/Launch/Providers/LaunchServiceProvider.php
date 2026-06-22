<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Kennofizet\AppHub\Core\Providers\ModuleServiceProvider;
use Kennofizet\AppHub\Modules\Launch\Console\HealthcheckAppsCommand;
use Kennofizet\AppHub\Modules\Launch\Services\AppEntryUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\AppHealthcheckService;
use Kennofizet\AppHub\Modules\Launch\Services\AppLaunchCallerUrlGuard;
use Kennofizet\AppHub\Modules\Launch\Services\AppUsageService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchService;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;

class LaunchServiceProvider extends ModuleServiceProvider
{
    public static function moduleKey(): string
    {
        return 'launch';
    }

    public function register(): void
    {
        $this->app->singleton(LaunchTokenService::class);
        $this->app->singleton(LaunchService::class);
        $this->app->singleton(AppUsageService::class);
        $this->app->singleton(AppHealthcheckService::class);
        $this->app->singleton(AppLaunchCallerUrlGuard::class);
        $this->app->singleton(AppEntryUrlGuard::class);
    }

    public function boot(): void
    {
        $this->loadModuleMigrations();

        if ($this->app->runningInConsole()) {
            $this->commands([
                HealthcheckAppsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $minutes = max(1, (int) config('apphub.healthcheck_schedule_minutes', 5));
            $schedule->command('apphub:healthcheck')->everyMinutes($minutes);
        });
    }
}
