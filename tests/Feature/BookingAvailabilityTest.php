<?php

namespace Tests\Feature;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Payments\PaymentGateway;
use App\Modules\Booking\Payments\PaymentGatewayFactory;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Master\Models\PricingTier;
use App\Modules\Master\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BookingAvailabilityTest extends TestCase
{
    public function test_booking_availability_disables_overlapping_slots_for_same_studio_and_date(): void
    {
        $unique = now()->format('YmdHis') . random_int(1000, 9999);
        $studio = Studio::create([
            'name' => 'Studio Booked Slot Test ' . $unique,
            'slug' => 'studio-booked-slot-test-' . $unique,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 250000,
            'is_active' => true,
        ]);

        $bookingDate = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->addDay()->startOfDay();

        Booking::create([
            'studio_id' => $studio->id,
            'pricing_tier_id' => $tier->id,
            'customer_name' => 'Test Customer',
            'customer_wa' => '081234567890',
            'code' => 'SBK-BOOKED-' . $unique,
            'start_time' => $bookingDate->setTime(10, 0),
            'end_time' => $bookingDate->setTime(12, 0),
            'duration_minutes' => 120,
            'status' => 'confirmed',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'source' => 'manual',
            'studio_price_at_booking' => 250000,
            'addon_total' => 0,
            'grand_total' => 250000,
            'payload' => [],
        ]);

        $slots = app(BookingService::class)->availability($studio->id, $bookingDate->format('Y-m-d'), 120);
        $slotsByStart = collect($slots)->keyBy(fn (array $slot) => $slot['start_time']->toIso8601String());

        $this->assertFalse($slotsByStart->get($bookingDate->setTime(10, 0)->toIso8601String())['available'] ?? true);
        $this->assertFalse($slotsByStart->get($bookingDate->setTime(11, 0)->toIso8601String())['available'] ?? true);
        $this->assertTrue($slotsByStart->get($bookingDate->setTime(12, 0)->toIso8601String())['available'] ?? false);
    }

    public function test_booking_availability_clamps_end_time_when_slot_crosses_closing_hour(): void
    {
        $unique = now()->format('YmdHis') . random_int(1000, 9999);
        $studio = Studio::create([
            'name' => 'Studio Closing Hour Test ' . $unique,
            'slug' => 'studio-closing-hour-test-' . $unique,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 180,
            'price' => 300000,
            'is_active' => true,
        ]);

        $bookingDate = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->addDay()->startOfDay();

        $slots = app(BookingService::class)->availability($studio->id, $bookingDate->format('Y-m-d'), 180);
        $slot = collect($slots)->first(fn (array $item) => $item['start_time']->equalTo($bookingDate->setTime(21, 0)));

        $this->assertNotNull($slot);
        $this->assertFalse($slot['available']);
        $this->assertTrue($slot['end_time']->equalTo($bookingDate->setTime(22, 0)));
        $this->assertInstanceOf(CarbonImmutable::class, $slot['start_time']);
        $this->assertInstanceOf(CarbonImmutable::class, $slot['end_time']);
    }

    public function test_confirmed_booking_update_keeps_confirmed_without_reissuing_payment(): void
    {
        $unique = now()->format('YmdHis') . random_int(1000, 9999);
        $studio = Studio::create([
            'name' => 'Studio Confirmed Edit Test ' . $unique,
            'slug' => 'studio-confirmed-edit-test-' . $unique,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 250000,
            'is_active' => true,
        ]);

        $newTier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 180,
            'price' => 350000,
            'is_active' => true,
        ]);

        $bookingDate = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(10, 0, 0);
        $booking = Booking::create([
            'studio_id' => $studio->id,
            'pricing_tier_id' => $tier->id,
            'customer_name' => 'Confirmed Customer',
            'customer_wa' => '081234567890',
            'code' => 'SBK-CONFIRMED-' . $unique,
            'start_time' => $bookingDate,
            'end_time' => $bookingDate->copy()->addMinutes(120),
            'duration_minutes' => 120,
            'status' => 'confirmed',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'source' => 'manual',
            'studio_price_at_booking' => 250000,
            'addon_total' => 0,
            'grand_total' => 250000,
            'payload' => ['payment_url' => 'https://example.test/old/' . $unique],
        ]);

        $updated = app(BookingService::class)->updateManual([
            'id' => $booking->id,
            'studio_id' => $studio->id,
            'pricing_tier_id' => $newTier->id,
            'customer_name' => 'Edited Confirmed Customer',
            'customer_wa' => '081234567891',
            'start_time' => $bookingDate->copy()->addHours(2)->toIso8601String(),
            'payment_method' => 'gateway',
            'addons' => [],
        ]);

        $this->assertSame('confirmed', $updated->status);
        $this->assertSame('paid', $updated->payment_status);
        $this->assertSame('https://example.test/old/' . $unique, $updated->payment_url);
        $this->assertSame($newTier->id, $updated->pricing_tier_id);
    }

    public function test_editing_booking_keeps_current_payment_state_without_reissuing_payment(): void
    {
        $unique = now()->format('YmdHis') . random_int(1000, 9999);
        $studio = Studio::create([
            'name' => 'Studio Edit Payment State Test ' . $unique,
            'slug' => 'studio-edit-payment-state-test-' . $unique,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 250000,
            'is_active' => true,
        ]);

        $newTier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 180,
            'price' => 350000,
            'is_active' => true,
        ]);

        $bookingDate = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(10, 0, 0);
        $booking = Booking::create([
            'studio_id' => $studio->id,
            'pricing_tier_id' => $tier->id,
            'customer_name' => 'Pending Customer',
            'customer_wa' => '081234567890',
            'code' => 'SBK-EDIT-PAYMENT-' . $unique,
            'start_time' => $bookingDate,
            'end_time' => $bookingDate->copy()->addMinutes(120),
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

        $updated = app(BookingService::class)->updateManual([
            'id' => $booking->id,
            'studio_id' => $studio->id,
            'pricing_tier_id' => $newTier->id,
            'customer_name' => 'Edited Pending Customer',
            'customer_wa' => '081234567891',
            'start_time' => $bookingDate->copy()->addHours(2)->toIso8601String(),
            'payment_method' => 'gateway',
            'addons' => [],
        ]);

        $this->assertSame('pending', $updated->status);
        $this->assertSame('unpaid', $updated->payment_status);
        $this->assertSame('cash', $updated->payment_method);
        $this->assertNull($updated->payment_url);
        $this->assertSame('Edited Pending Customer', $updated->customer_name);
    }

    public function test_booking_update_regenerates_payment_link_when_gateway_selected(): void
    {
        $unique = now()->format('YmdHis') . random_int(1000, 9999);
        $studio = Studio::create([
            'name' => 'Studio Update Payment Test ' . $unique,
            'slug' => 'studio-update-payment-test-' . $unique,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 250000,
            'is_active' => true,
        ]);

        $newTier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 180,
            'price' => 350000,
            'is_active' => true,
        ]);

        $bookingDate = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(10, 0, 0);
        $booking = Booking::create([
            'studio_id' => $studio->id,
            'pricing_tier_id' => $tier->id,
            'customer_name' => 'Old Customer',
            'customer_wa' => '081234567890',
            'code' => 'SBK-UPDATE-' . $unique,
            'start_time' => $bookingDate,
            'end_time' => $bookingDate->copy()->addMinutes(120),
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

        app()->instance(PaymentGatewayFactory::class, new class extends PaymentGatewayFactory {
            public function __construct()
            {
                parent::__construct(app(\App\Modules\Booking\Payments\IpaymuGateway::class));
            }

            public function make(): PaymentGateway
            {
                return new class implements PaymentGateway {
                    public function create(\App\Modules\Booking\Models\Booking $booking): array
                    {
                        $booking->update([
                            'payment_reference' => 'ref-' . $booking->code,
                            'payload' => array_merge($booking->payload ?? [], [
                                'payment_gateway' => 'ipy',
                                'payment_url' => 'https://example.test/pay/' . $booking->code,
                            ]),
                        ]);

                        return ['reference' => 'ref-' . $booking->code, 'url' => 'https://example.test/pay/' . $booking->code];
                    }

                    public function handleCallback(array $payload, string $rawBody, string $signature, ?string $externalId = null, ?string $timestamp = null): void
                    {
                    }
                };
            }
        });

        $updated = app(BookingService::class)->updateManual([
            'id' => $booking->id,
            'studio_id' => $studio->id,
            'pricing_tier_id' => $newTier->id,
            'customer_name' => 'Updated Customer',
            'customer_wa' => '081234567891',
            'start_time' => $bookingDate->copy()->addHours(2)->toIso8601String(),
            'payment_method' => 'gateway',
            'addons' => [],
        ]);

        $this->assertSame('Updated Customer', $updated->customer_name);
        $this->assertSame('gateway', $updated->payment_method);
        $this->assertSame('pending', $updated->status);
        $this->assertSame('https://example.test/pay/' . $updated->code, $updated->payment_url);
        $this->assertSame($newTier->id, $updated->pricing_tier_id);
    }
}
