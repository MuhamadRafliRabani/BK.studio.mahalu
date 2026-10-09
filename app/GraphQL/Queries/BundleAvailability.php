<?php

namespace App\GraphQL\Queries;

use App\Modules\Booking\Services\BookingService;

class BundleAvailability
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function __invoke($_, array $args): array
    {
        return $this->bookingService->bundleAvailability(
            (int) $args['bundle_id'],
            $args['date'],
            isset($args['exclude_booking_id']) ? (int) $args['exclude_booking_id'] : null,
        );
    }
}
