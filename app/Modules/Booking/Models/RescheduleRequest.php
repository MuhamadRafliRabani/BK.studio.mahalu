<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RescheduleRequest extends Model
{
    use HasAuditColumns;
    const CREATED_AT = 'cretime';
    const UPDATED_AT = 'modtime';
    protected $table = 'msb_bk_reschedule_requests';

    protected $fillable = [
        'booking_id', 'old_start_time', 'old_end_time',
        'new_start_time', 'new_end_time', 'status',
        'requested_by', 'confirmation_deadline',
    ];

    protected $casts = [
        'old_start_time' => 'datetime',
        'old_end_time' => 'datetime',
        'new_start_time' => 'datetime',
        'new_end_time' => 'datetime',
        'confirmation_deadline' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}