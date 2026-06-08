<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class AppUsageLog extends Model
{
    use UsesAppHubTable;

    public const UPDATED_AT = null;

    public const ACTION_APP_OPEN = 'app_open';
    public const ACTION_ERROR = 'error';

    protected $fillable = [
        'app_id',
        'user_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'user_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('app_usage_logs_table', 'apphub_app_usage_logs');
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
