<?php

namespace Tests\Feature;

use App\GraphQL\Mutations\BookingMutations;
use App\Modules\Booking\Models\Booking;
use App\Modules\Master\Models\PricingTier;
use App\Modules\Master\Models\Studio;
use Tests\TestCase;

class BookingMutationDeleteTest extends TestCase
{
    public function test_delete_booking_mutation_removes_booking_record(): void
    {
        $unique = now()->format('YmdHis') . random_int(1000, 9999);

        $studio = Studio::create([
            'name' => 'Studio Delete Test',
            'slug' => 'studio-delete-test-' . $unique,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 250000,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'studio_id' => $studio->id,
            'pricing_tier_id' => $tier->id,
            'customer_name' => 'Test Delete',
            'customer_wa' => '081234567890',
            'code' => 'SBK-DELETE-TEST-' . $unique,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(12, 0),
            'duration_minutes' => 120,
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'source' => 'manual',
            'studio_price_at_booking' => 250000,
            'addon_total' => 0,
            'grand_total' => 250000,
            'payload' => [],
        ]);

        $mutation = app(BookingMutations::class);
        $deleted = $mutation->delete([], ['id' => $booking->id]);

        $this->assertSame($booking->id, $deleted->id);
        $this->assertDatabaseMissing('msb_bk_bookings', ['id' => $booking->id]);
    }
}
