<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class AppBridgeConsent extends Model
{
    use UsesAppHubTable;

    protected $fillable = [
        'app_id',
        'user_id',
        'scope',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('app_bridge_consents_table', 'apphub_app_bridge_consents');
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
