<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class AppBridgeParentVersionApproval extends Model
{
    use UsesAppHubTable;

    protected $fillable = [
        'app_id',
        'bundle_version',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'approved_by_user_id' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable(
            'app_bridge_parent_approvals_table',
            'apphub_app_bridge_parent_approvals',
        );
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
