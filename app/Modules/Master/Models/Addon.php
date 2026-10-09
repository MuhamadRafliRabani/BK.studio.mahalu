<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Modules\Master\Models\Studio;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    use HasAuditColumns;
    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected $table = 'msb_ms_addons';

    protected $fillable = [
        'name', 'icon', 'pricing_type', 'flat_price', 'is_active',
    ];

    protected $casts = [
        'flat_price' => 'integer',
        'is_active' => 'boolean',
    ];

    public function studios()
    {
        return $this->belongsToMany(Studio::class, 'msb_ms_addon_studios');
    }

    public function prices()
    {
        return $this->hasMany(AddonPrice::class);
    }

    public function resources()
    {
        return $this->hasMany(Resource::class);
    }
}