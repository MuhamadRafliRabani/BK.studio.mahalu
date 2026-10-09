<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Modules\Master\Models\Addon;
use App\Modules\Master\Models\Resource;
use Illuminate\Database\Eloquent\Model;

class BookingAddon extends Model
{
    use HasAuditColumns;
    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected $table = 'msb_bk_booking_addons';

    protected $fillable = [
        'booking_id', 'addon_id', 'resource_id', 'duration_minutes', 'price_at_booking', 'is_bundle_item',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price_at_booking' => 'integer',
        'is_bundle_item' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }
}