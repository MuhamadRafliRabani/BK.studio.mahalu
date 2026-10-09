<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasGeneratedCode;
use App\Modules\Booking\Models\Booking;
use App\Modules\Master\Models\Addon;
use App\Modules\Master\Models\PricingTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Studio extends Model
{
    use HasAuditColumns,HasGeneratedCode;
        const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected static function codePrefix(): string { return 'STD'; }

    protected $table = 'msb_ms_studios';

    protected $fillable = [
        'name', 'description', 'capacity', 'size_sqm', 'thumbnail', 'slug', 'sort_order', 'is_active', 'code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'size_sqm' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $appends = ['thumbnail_url'];

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail ? Storage::disk('public')->url($this->thumbnail) : null;
    }

    public function pricingTiers()
    {
        return $this->hasMany(PricingTier::class);
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'msb_ms_addon_studios');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function photos()
    {
        return $this->hasMany(StudioPhoto::class)->orderBy('sort_order');
    }
}