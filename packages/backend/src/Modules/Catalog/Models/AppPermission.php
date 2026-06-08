<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;
use Kennofizet\AppHub\Modules\Catalog\Support\AppPermissionType;

class AppPermission extends Model
{
    use UsesAppHubTable;

    protected $fillable = [
        'app_id',
        'user_id',
        'permission',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('app_permissions_table', 'apphub_app_permissions');
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
