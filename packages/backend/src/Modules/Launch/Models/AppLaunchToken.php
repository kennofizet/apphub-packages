<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class AppLaunchToken extends Model
{
    use UsesAppHubTable;

    protected $fillable = [
        'app_id',
        'user_id',
        'token_hash',
        'session_id',
        'bundle_version',
        'scopes_granted',
        'expires_at',
        'used_at',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'user_id' => 'integer',
        'scopes_granted' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('app_launch_tokens_table', 'apphub_app_launch_tokens');
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
