<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class AppZoneAccess extends Model
{
    use UsesAppHubTable;

    protected $fillable = [
        'app_id',
        'zone_id',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'zone_id' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('app_zone_access_table', 'apphub_app_zone_access');
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
