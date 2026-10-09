<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StudioPhoto extends Model
{
    use HasAuditColumns;

    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';

    protected $table = 'msb_ms_studio_photos';

    protected $fillable = [
        'studio_id',
        'path',
        'sort_order',
    ];

    protected $appends = ['url'];

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
