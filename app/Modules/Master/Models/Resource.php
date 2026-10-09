<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasGeneratedCode;
use App\Modules\Booking\Models\BookingAddon;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasAuditColumns, HasGeneratedCode;

    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected static function codePrefix(): string { return 'RES'; }


    protected $table = 'msb_ms_resources';

    protected $fillable = [
        'addon_id', 'name', 'is_active', 'code', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    public function bookingAddons()
    {
        return $this->hasMany(BookingAddon::class);
    }
}