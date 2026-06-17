<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class AppBridgeConsentIntent extends Model
{
    use UsesAppHubTable;

    protected $fillable = [
        'app_id',
        'user_id',
        'token_hash',
        'bundle_version',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'user_id' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('app_bridge_consent_intents_table', 'apphub_app_bridge_consent_intents');
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
