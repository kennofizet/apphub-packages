<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kennofizet\AppHub\Modules\Catalog\Models\Concerns\UsesAppHubTable;

class AppVersion extends Model
{
    use UsesAppHubTable;

    protected $fillable = [
        'app_id',
        'version',
        'bundle_path',
        'bundle_hash',
        'bundle_entry',
        'manifest',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'app_id' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'manifest' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::apphubTable('app_versions_table', 'apphub_app_versions');
        parent::__construct($attributes);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
