<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasGeneratedCode;
use App\Modules\Master\Models\Bundle;
use App\Modules\Master\Models\Studio;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasAuditColumns,HasGeneratedCode;
        const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected static function codePrefix(): string { return 'SBK'; }

    protected $table = 'msb_bk_bookings';

    protected $fillable = [
        'studio_id', 'pricing_tier_id', 'customer_name', 'customer_wa', 'code',
        'start_time', 'end_time', 'duration_minutes', 'status',
        'studio_price_at_booking', 'addon_total', 'grand_total', 'payload',
        'bundle_id', 'bundle_name_at_booking', 'bundle_price_at_booking', 'discount_total',
        'payment_method', 'payment_status', 'payment_expires_at',
        'payment_reference', 'source',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'payment_expires_at' => 'datetime',
        'payload' => 'array',
        'duration_minutes' => 'integer',
        'studio_price_at_booking' => 'integer',
        'addon_total' => 'integer',
        'grand_total' => 'integer',
        'bundle_price_at_booking' => 'integer',
        'discount_total' => 'integer',
    ];

    protected $appends = ['payment_url'];

    public function getPaymentUrlAttribute(): ?string
    {
        return $this->payload['payment_url'] ?? null;
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class)->withTrashed();
    }

    public function pricingTier()
    {
        return $this->belongsTo(\App\Modules\Master\Models\PricingTier::class);
    }

    public function addons()
    {
        return $this->hasMany(BookingAddon::class);
    }

    public function rescheduleRequests()
    {
        return $this->hasMany(RescheduleRequest::class);
    }

    public function scopeOverlapping($query, $studioId, $start, $end)
    {
        return $query->where('studio_id', $studioId)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);
    }
}