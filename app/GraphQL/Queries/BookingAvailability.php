<?php

namespace App\GraphQL\Queries;

use App\Modules\Booking\Services\BookingService;

class BookingAvailability
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function __invoke($_, array $args): array
    {
        return $this->bookingService->availability(
            (int) $args['studio_id'],
            $args['date'],
            (int) $args['duration_minutes'],
        );
    }
}
