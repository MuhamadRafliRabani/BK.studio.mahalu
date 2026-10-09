<?php

namespace Tests\Feature;

use App\Modules\Booking\Services\BookingService;
use App\Modules\Master\Models\Addon;
use App\Modules\Master\Models\AddonPrice;
use App\Modules\Master\Models\Bundle;
use App\Modules\Master\Models\PricingTier;
use App\Modules\Master\Models\Resource;
use App\Modules\Master\Models\Studio;
use App\Modules\Master\Services\BundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BundleBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_price_must_be_lower_than_the_normal_package_price(): void
    {
        $catalog = $this->createCatalog();
        $bundleInput = $this->bundleInput($catalog, 13000);

        try {
            app(BundleService::class)->create($bundleInput);
            $this->fail('A bundle priced at its normal total should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bundle_price', $exception->errors());
        }
    }

    public function test_bundle_booking_uses_package_price_and_keeps_normal_item_price_snapshots(): void
    {
        $catalog = $this->createCatalog(withExtraAddon: true);
        $bundle = app(BundleService::class)->create($this->bundleInput($catalog, 9000));
        $start = now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(10, 0);

        $booking = app(BookingService::class)->createManual([
            'bundle_id' => $bundle->id,
            'customer_name' => 'Bundle Customer',
            'customer_wa' => '081234567890',
            'start_time' => $start->toIso8601String(),
            'payment_method' => 'cash',
            'addons' => [
                ['addon_id' => $catalog['extra_addon']->id, 'duration_minutes' => null],
            ],
        ]);

        $this->assertSame((int) $catalog['package_addon_price']->price, (int) $booking->addon_total - (int) $catalog['extra_addon']->flat_price);
        $this->assertSame(9000, (int) $booking->bundle_price_at_booking);
        $this->assertSame(4000, (int) $booking->discount_total);
        $this->assertSame(9500, (int) $booking->grand_total);
        $this->assertDatabaseHas('msb_bk_booking_addons', [
            'booking_id' => $booking->id,
            'addon_id' => $catalog['package_addon']->id,
            'price_at_booking' => 3000,
            'is_bundle_item' => true,
        ]);
        $this->assertDatabaseHas('msb_bk_booking_addons', [
            'booking_id' => $booking->id,
            'addon_id' => $catalog['extra_addon']->id,
            'price_at_booking' => 500,
            'is_bundle_item' => false,
        ]);
    }

    public function test_existing_bundle_booking_can_be_rescheduled_after_the_promo_ends_without_changing_its_price(): void
    {
        $this->travelTo(now(config('app.timezone', 'Asia/Jakarta'))->startOfDay()->setTime(8, 0));
        $catalog = $this->createCatalog();
        $bundle = app(BundleService::class)->create($this->bundleInput(
            $catalog,
            9000,
            validFrom: now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
            validUntil: now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
        ));
        $booking = app(BookingService::class)->createManual([
            'bundle_id' => $bundle->id,
            'customer_name' => 'Reschedule Customer',
            'customer_wa' => '081234567890',
            'start_time' => now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(10, 0)->toIso8601String(),
            'payment_method' => 'cash',
            'addons' => [],
        ]);

        $this->travelTo(now(config('app.timezone', 'Asia/Jakarta'))->addDays(2)->setTime(8, 0));
        $updated = app(BookingService::class)->updateManual([
            'id' => $booking->id,
            'bundle_id' => $bundle->id,
            'customer_name' => 'Reschedule Customer',
            'customer_wa' => '081234567890',
            'start_time' => now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(11, 0)->toIso8601String(),
            'addons' => [],
        ]);

        $this->assertSame($booking->bundle_price_at_booking, $updated->bundle_price_at_booking);
        $this->assertSame($booking->discount_total, $updated->discount_total);
        $this->assertSame($booking->grand_total, $updated->grand_total);
        $this->assertSame('confirmed', $updated->status);
    }

    public function test_total_quota_is_released_by_expired_pending_bookings_and_blocks_new_sales_at_limit(): void
    {
        $catalog = $this->createCatalog();
        $input = $this->bundleInput($catalog, 9000);
        $input['quota_limit'] = 1;
        $input['quota_period'] = 'total';
        $bundle = app(BundleService::class)->create($input);
        $start = now(config('app.timezone', 'Asia/Jakarta'))->addDay()->setTime(10, 0);

        $booking = app(BookingService::class)->createManual([
            'bundle_id' => $bundle->id,
            'customer_name' => 'Quota Customer',
            'customer_wa' => '081234567890',
            'start_time' => $start->toIso8601String(),
            'payment_method' => 'gateway',
            'addons' => [],
        ]);

        $this->assertSame('pending', $booking->status);
        $this->assertSame(1, $bundle->fresh()->quotaUsed());
        $this->assertFalse($bundle->fresh()->isSellable());

        $booking->update([
            'status' => 'expired',
            'payment_status' => 'expired',
        ]);

        $this->assertSame(0, $bundle->fresh()->quotaUsed());
        $this->assertTrue($bundle->fresh()->isSellable());
    }

    /** @return array<string, mixed> */
    private function createCatalog(bool $withExtraAddon = false): array
    {
        $suffix = bin2hex(random_bytes(4));
        $studio = Studio::create([
            'name' => "Bundle Studio {$suffix}",
            'slug' => "bundle-studio-{$suffix}",
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $tier = PricingTier::create([
            'studio_id' => $studio->id,
            'duration_minutes' => 120,
            'price' => 10000,
            'is_active' => true,
        ]);
        $packageAddon = Addon::create([
            'name' => "Bundle Add-on {$suffix}",
            'pricing_type' => 'per_tier',
            'is_active' => true,
        ]);
        $packageAddon->studios()->attach($studio->id);
        $packageAddonPrice = AddonPrice::create([
            'addon_id' => $packageAddon->id,
            'duration_minutes' => 60,
            'price' => 3000,
            'is_active' => true,
        ]);
        Resource::create([
            'addon_id' => $packageAddon->id,
            'name' => "Bundle Resource {$suffix}",
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $extraAddon = null;
        if ($withExtraAddon) {
            $extraAddon = Addon::create([
                'name' => "Extra Add-on {$suffix}",
                'pricing_type' => 'flat',
                'flat_price' => 500,
                'is_active' => true,
            ]);
            $extraAddon->studios()->attach($studio->id);
            Resource::create([
                'addon_id' => $extraAddon->id,
                'name' => "Extra Resource {$suffix}",
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        return [
            'studio' => $studio,
            'tier' => $tier,
            'package_addon' => $packageAddon,
            'package_addon_price' => $packageAddonPrice,
            'extra_addon' => $extraAddon,
        ];
    }

    /** @return array<string, mixed> */
    private function bundleInput(array $catalog, int $bundlePrice, ?string $validFrom = null, ?string $validUntil = null): array
    {
        return [
            'name' => 'Studio Hemat',
            'description' => 'Paket studio dan layanan tambahan.',
            'studio_id' => $catalog['studio']->id,
            'pricing_tier_id' => $catalog['tier']->id,
            'bundle_price' => $bundlePrice,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'quota_limit' => null,
            'quota_period' => null,
            'sort_order' => 1,
            'is_active' => true,
            'addons' => [[
                'addon_id' => $catalog['package_addon']->id,
                'duration_minutes' => 60,
            ]],
        ];
    }
}
