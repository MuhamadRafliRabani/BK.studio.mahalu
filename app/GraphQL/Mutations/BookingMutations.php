<?php

namespace App\GraphQL\Mutations;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;

class BookingMutations
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function createManual($_, array $args): Booking
    {
        return $this->bookingService->createManual([
            'studio_id' => $args['studio_id'],
            'pricing_tier_id' => $args['pricing_tier_id'],
            'bundle_id' => $args['bundle_id'] ?? null,
            'customer_name' => $args['customer_name'],
            'customer_wa' => $args['customer_wa'],
            'start_time' => $args['start_time'],
            'payment_method' => $args['payment_method'] ?? 'cash',
            'addons' => $args['addons'] ?? [],
        ]);
    }

    public function updateManual($_, array $args): Booking
    {
        return $this->bookingService->updateManual([
            'id' => $args['id'],
            'studio_id' => $args['studio_id'],
            'pricing_tier_id' => $args['pricing_tier_id'],
            'bundle_id' => $args['bundle_id'] ?? null,
            'customer_name' => $args['customer_name'],
            'customer_wa' => $args['customer_wa'],
            'start_time' => $args['start_time'],
            'payment_method' => $args['payment_method'] ?? 'cash',
            'addons' => $args['addons'] ?? [],
        ]);
    }

    public function delete($_, array $args): Booking
    {
        return $this->bookingService->deleteBooking((int) $args['id']);
    }

    public function cancel($_, array $args): Booking
    {
        return $this->bookingService->cancelBooking((int) $args['id']);
    }
}
