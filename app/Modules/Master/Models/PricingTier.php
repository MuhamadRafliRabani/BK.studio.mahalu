<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Modules\Master\Models\Studio;
use Illuminate\Database\Eloquent\Model;

class PricingTier extends Model
{
    use HasAuditColumns;
    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected $table = 'msb_ms_pricing_tiers';

    protected $fillable = [
        'studio_id', 'duration_minutes', 'price', 'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'integer',
        'is_active' => 'boolean',
    ];

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }
}