<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;
use Kennofizet\AppHub\Modules\Catalog\Support\AppRuntimeType;
use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use Kennofizet\AppHub\Modules\Launch\Models\AppLaunchToken;
use Kennofizet\AppHub\Modules\Launch\Models\AppUsageLog;

class App extends Model
{
    use SoftDeletes;
    use UsesAppHubTable;

    protected $fillable = [
        'owner_user_id',
        'slug',
        'name',
        'short_description',
        'icon',
        'status',
        'runtime_type',
        'entry_url',
        'healthcheck_url',
        'manifest',
    ];

    protected $casts = [
        'owner_user_id' => 'integer',
        'manifest' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('apps_table', 'apphub_apps');
        parent::__construct($attributes);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AppPermission::class, 'app_id');
    }

    public function zoneAccess(): HasMany
    {
        return $this->hasMany(AppZoneAccess::class, 'app_id');
    }

    public function launchTokens(): HasMany
    {
        return $this->hasMany(AppLaunchToken::class, 'app_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AppUsageLog::class, 'app_id');
    }

    public function isActive(): bool
    {
        return $this->status === AppStatus::ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === AppStatus::DRAFT;
    }

    public function isDisabled(): bool
    {
        return AppStatus::isDisabled((string) $this->status);
    }

    public function canLaunch(): bool
    {
        return AppStatus::canLaunch((string) $this->status);
    }

    public function usesIframeRuntime(): bool
    {
        return $this->runtime_type === AppRuntimeType::IFRAME;
    }
}
