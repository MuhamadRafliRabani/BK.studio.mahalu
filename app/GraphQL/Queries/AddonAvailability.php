<?php

namespace App\GraphQL\Queries;

use App\Modules\Booking\Services\BookingService;

class AddonAvailability
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function __invoke($_, array $args): array
    {
        return $this->bookingService->addonAvailability(
            (int) $args['studio_id'],
            (int) $args['addon_id'],
            $args['start_time'],
            (int) $args['duration_minutes'],
        );
    }
}