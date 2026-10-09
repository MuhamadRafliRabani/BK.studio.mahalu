<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;

class AddonPrice extends Model
{
    use HasAuditColumns;
    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected $table = 'msb_ms_addon_prices';

    protected $fillable = [
        'addon_id', 'duration_minutes', 'price', 'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'integer',
        'is_active' => 'boolean',
    ];

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }
}