<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Modules\Master\Models\Studio;
use Illuminate\Database\Eloquent\Model;

class AddonStudio extends Model
{
    use HasAuditColumns;
    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected $table = 'msb_ms_addon_studios';

    protected $fillable = [
        'addon_id', 'studio_id',
    ];

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }
}