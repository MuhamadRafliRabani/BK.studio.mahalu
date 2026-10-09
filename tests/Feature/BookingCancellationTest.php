<?php

namespace Tests\Feature;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Master\Models\PricingTier;
use App\Modules\Master\Models\Studio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_a_booking_releases_its_studio_slot(): void
    {
        $studio = Studio::create([
            'name' => 'Cancellation Studio',
            'slug' => 'cancellation-studio',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 250000,
            'is_active' => true,
        ]);
        $start = now(config('app.timezone', 'Asia/Jakarta'))
            ->addDay()
            ->setTime(10, 0);
        $booking = Booking::create([
            'studio_id' => $studio->id,
            'pricing_tier_id' => $tier->id,
            'customer_name' => 'Cancel Customer',
            'customer_wa' => '081234567890',
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes(120),
            'duration_minutes' => 120,
            'status' => 'pending',
            'payment_method' => 'gateway',
            'payment_status' => 'unpaid',
            'source' => 'manual',
            'studio_price_at_booking' => 250000,
            'addon_total' => 0,
            'grand_total' => 250000,
            'payload' => [],
        ]);

        $service = app(BookingService::class);
        $availability = fn () => $service->availability(
            $studio->id,
            $start->toDateString(),
            $tier->duration_minutes,
        );
        $this->assertFalse($availability()[0]['available']);

        $cancelled = $service->cancelBooking($booking->id);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('unpaid', $cancelled->payment_status);
        $this->assertTrue($availability()[0]['available']);
        $this->assertDatabaseHas('msb_bk_bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancelling_a_paid_booking_keeps_its_payment_status(): void
    {
        $booking = $this->createBooking('confirmed', 'paid');

        $cancelled = app(BookingService::class)->cancelBooking($booking->id);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('paid', $cancelled->payment_status);
    }

    public function test_completed_bookings_cannot_be_cancelled(): void
    {
        $booking = $this->createBooking('completed', 'paid');

        $this->expectException(ValidationException::class);

        app(BookingService::class)->cancelBooking($booking->id);
    }

    private function createBooking(string $status, string $paymentStatus): Booking
    {
        $unique = bin2hex(random_bytes(5));
        $studio = Studio::create([
            'name' => "Cancellation Studio {$unique}",
            'slug' => "cancellation-studio-{$unique}",
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 250000,
            'is_active' => true,
        ]);
        $start = now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(10, 0);

        return Booking::create([
            'studio_id' => $studio->id,
            'pricing_tier_id' => $tier->id,
            'customer_name' => 'Cancel Customer',
            'customer_wa' => '081234567890',
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes(120),
            'duration_minutes' => 120,
            'status' => $status,
            'payment_method' => 'cash',
            'payment_status' => $paymentStatus,
            'source' => 'manual',
            'studio_price_at_booking' => 250000,
            'addon_total' => 0,
            'grand_total' => 250000,
            'payload' => [],
        ]);
    }
}
