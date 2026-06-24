<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class UserNotification extends Model
{
    use UsesAppHubTable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'app_id',
        'app_slug',
        'app_name',
        'app_icon',
        'title',
        'body',
        'read_at',
        'dismissed_at',
        'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'app_id' => 'integer',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('user_notifications_table', 'apphub_user_notifications');
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
