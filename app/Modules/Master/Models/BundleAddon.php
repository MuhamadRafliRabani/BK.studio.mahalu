<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Modules\Master\Models\AddonPrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleAddon extends Model
{
    use HasAuditColumns;

    public const CREATED_AT = 'cretime';
    public const UPDATED_AT = 'modtime';

    protected $table = 'msb_ms_bundle_addons';

    protected $fillable = ['bundle_id', 'addon_id', 'duration_minutes'];

    protected $casts = ['duration_minutes' => 'integer'];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function getNormalPriceAttribute(): int
    {
        $addon = $this->addon;
        if (! $addon) {
            return 0;
        }

        if ($addon->pricing_type === 'flat') {
            return (int) ($addon->flat_price ?? 0);
        }

        return (int) AddonPrice::query()
            ->where('addon_id', $addon->id)
            ->where('duration_minutes', $this->duration_minutes)
            ->where('is_active', true)
            ->value('price');
    }
}
