<?php

namespace App\Console\Commands;

use App\Modules\Booking\Models\Booking;
use Illuminate\Console\Command;

class AdvanceBookingStatuses extends Command
{
    protected $signature = 'booking:advance-statuses';
    protected $description = 'Expire unpaid bookings and complete finished bookings';

    public function handle(): int
    {
        $expired = Booking::query()
            ->where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $completed = Booking::query()
            ->where('status', 'confirmed')
            ->where('end_time', '<=', now())
            ->update(['status' => 'completed']);

        $this->info("Expired: {$expired}; completed: {$completed}.");

        return self::SUCCESS;
    }
}
