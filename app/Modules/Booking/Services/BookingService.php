<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Payments\PaymentGatewayFactory;
use App\Modules\Booking\Models\BookingAddon;
use App\Modules\Master\Models\Addon;
use App\Modules\Master\Models\AddonPrice;
use App\Modules\Master\Models\Bundle;
use App\Modules\Master\Models\Resource;
use App\Modules\Master\Models\Studio;
use App\Modules\Master\Models\PricingTier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private readonly PaymentGatewayFactory $paymentGateways)
    {
    }

    private const OPENING_HOUR = 10;
    private const CLOSING_HOUR = 22;
    private const ACTIVE_STATUSES = ['pending', 'confirmed'];

    public function availability(int $studioId, string $date, int $durationMinutes, ?int $excludeBookingId = null): array
    {
        $studio = Studio::query()
            ->whereKey($studioId)
            ->where('is_active', true)
            ->firstOrFail();

        $this->assertDurationWithinOperatingHours($durationMinutes);
        $day = CarbonImmutable::parse($date, config('app.timezone', 'Asia/Jakarta'))->startOfDay();

        return $this->buildDailyAvailability($studio->id, $day, $durationMinutes, $excludeBookingId);
    }

    public function bundleAvailability(int $bundleId, string $date, ?int $excludeBookingId = null): array
    {
        $bundle = Bundle::withTrashed()->with([
            'studio',
            'pricingTier',
            'bundleAddons.addon.resources',
        ])->whereKey($bundleId)->firstOrFail();
        $isExistingBundleBooking = $excludeBookingId !== null
            && Booking::query()->whereKey($excludeBookingId)->where('bundle_id', $bundleId)->exists();

        if (! $isExistingBundleBooking && ! $bundle->isSellable()) {
            return [];
        }

        if (! $bundle->studio || ! $bundle->pricingTier) {
            return [];
        }

        $slots = $this->availability(
            (int) $bundle->studio_id,
            $date,
            (int) $bundle->pricingTier->duration_minutes,
            $excludeBookingId,
        );

        foreach ($slots as &$slot) {
            if (! $slot['available']) {
                continue;
            }

            foreach ($bundle->bundleAddons as $bundleAddon) {
                if (! $bundleAddon->addon) {
                    $slot['available'] = false;
                    $slot['reason'] = 'Add-on bundle tidak lagi tersedia.';
                    break;
                }

                $availability = $this->addonAvailability(
                    (int) $bundle->studio_id,
                    (int) $bundleAddon->addon_id,
                    $slot['start_time']->toIso8601String(),
                    (int) $bundle->pricingTier->duration_minutes,
                    allowInactive: $isExistingBundleBooking,
                    excludeBookingId: $excludeBookingId,
                );
                if (! $availability['available']) {
                    $slot['available'] = false;
                    $slot['reason'] = $availability['reason'] ?? 'Resource add-on bundle tidak tersedia.';
                    break;
                }
            }
        }
        unset($slot);

        return $slots;
    }

    private function buildDailyAvailability(int $studioId, CarbonImmutable $day, int $durationMinutes, ?int $excludeBookingId = null): array
    {
        $slots = [];
        $closingTime = $day->setTime(self::CLOSING_HOUR, 0);

        for ($hour = self::OPENING_HOUR; $hour < self::CLOSING_HOUR; $hour++) {
            $start = $day->setTime($hour, 0);
            $end = $start->addMinutes($durationMinutes);
            $effectiveEnd = $end->gt($closingTime) ? $closingTime : $end;
            $available = $end->lte($closingTime);
            $reason = $available ? null : 'Waktu selesai melewati jam operasional.';

            if ($available && $start->isPast()) {
                $available = false;
                $reason = 'Waktu mulai sudah lewat.';
            }

            if ($available && $this->hasOverlap(Booking::query(), 'studio_id', $studioId, $start, $end, $excludeBookingId)) {
                $available = false;
                $reason = 'Studio sudah memiliki booking pada jam tersebut.';
            }

            $slots[] = [
                'start_time' => $start,
                'end_time' => $effectiveEnd,
                'available' => $available,
                'reason' => $reason,
            ];
        }

        return $slots;
    }

    public function addonAvailability(
        int $studioId,
        int $addonId,
        string $startTime,
        int $durationMinutes,
        bool $allowInactive = false,
        ?int $excludeBookingId = null,
    ): array
    {
        $studio = Studio::query()->whereKey($studioId)->where('is_active', true)->firstOrFail();
        $addon = Addon::query()
            ->whereKey($addonId)
            ->when(! $allowInactive, fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('studios', fn (Builder $query) => $query->whereKey($studio->id)->where('is_active', true))
            ->with(['resources' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
            ->firstOrFail();
        $start = CarbonImmutable::parse($startTime, config('app.timezone', 'Asia/Jakarta'));
        $end = $start->addMinutes($durationMinutes);
        $available = [];
        $nextAvailableAt = null;

        foreach ($addon->resources as $resource) {
            $overlap = $resource->bookingAddons()
                ->whereHas('booking', function (Builder $query) use ($start, $end, $excludeBookingId): void {
                    $bookingQuery = $this->activeBookingQuery($query)
                        ->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                    if ($excludeBookingId !== null) {
                        $bookingQuery->whereKeyNot($excludeBookingId);
                    }
                })
                ->with('booking:id,end_time')
                ->get();
            if ($overlap->isEmpty()) {
                $available[] = $resource->name;
                continue;
            }
            $candidate = $overlap->pluck('booking.end_time')->filter()->min();
            if ($candidate && (!$nextAvailableAt || $candidate->lt($nextAvailableAt))) {
                $nextAvailableAt = $candidate;
            }
        }

        return [
            'addon_id' => $addon->id,
            'available' => count($available) > 0,
            'resource_name' => $available[0] ?? null,
            'available_resource_count' => count($available),
            'next_available_at' => $nextAvailableAt,
            'reason' => $addon->resources->isEmpty()
                ? 'Belum ada resource aktif untuk addon ini.'
                : (count($available) ? null : 'Semua resource sedang digunakan pada jam tersebut.'),
        ];
    }

    public function createManual(array $input): Booking
    {
        return DB::transaction(function () use ($input): Booking {
            $bundle = null;
            if (! empty($input['bundle_id'])) {
                $bundle = Bundle::query()->with([
                    'studio',
                    'pricingTier',
                    'bundleAddons.addon.studios',
                    'bundleAddons.addon.prices',
                ])->whereKey($input['bundle_id'])->lockForUpdate()->firstOrFail();
                if (! $bundle->isSellable()) {
                    throw ValidationException::withMessages([
                        'bundle_id' => 'Bundle sudah tidak tersedia atau kuotanya habis.',
                    ]);
                }
            }

            $studioId = $bundle?->studio_id ?? ($input['studio_id'] ?? null);
            $pricingTierId = $bundle?->pricing_tier_id ?? ($input['pricing_tier_id'] ?? null);
            if (! $studioId || ! $pricingTierId) {
                throw ValidationException::withMessages([
                    'studio_id' => 'Studio dan tier harga wajib dipilih.',
                ]);
            }
            if ($bundle && ((isset($input['studio_id']) && (int) $input['studio_id'] !== (int) $studioId)
                || (isset($input['pricing_tier_id']) && (int) $input['pricing_tier_id'] !== (int) $pricingTierId))) {
                throw ValidationException::withMessages([
                    'bundle_id' => 'Studio dan durasi booking harus mengikuti isi bundle.',
                ]);
            }

            $studio = Studio::query()
                ->whereKey($studioId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $tier = PricingTier::query()
                ->whereKey($pricingTierId)
                ->where('studio_id', $studio->id)
                ->where('is_active', true)
                ->firstOrFail();

            $timezone = config('app.timezone', 'Asia/Jakarta');
            $start = CarbonImmutable::parse($input['start_time'], $timezone);
            $end = $start->addMinutes($tier->duration_minutes);
            $this->validateSchedule($start, $end);

            if ($this->hasOverlap(Booking::query(), 'studio_id', $studio->id, $start, $end)) {
                throw ValidationException::withMessages(['start_time' => 'Studio sudah dibooking pada jam tersebut.']);
            }

            $extraAddons = $input['addons'] ?? [];
            $bundleAddonInputs = $bundle ? $this->bundleAddonInputs($bundle) : [];
            $this->assertNoDuplicateBundleAddons($bundleAddonInputs, $extraAddons);
            $selectedAddons = $this->resolveAddons(
                [...$bundleAddonInputs, ...$extraAddons],
                $studio,
                $start,
                $end,
                $tier->duration_minutes,
            );
            $paymentMethod = $input['payment_method'] ?? 'cash';
            if (!in_array($paymentMethod, ['cash', 'gateway'], true)) {
                throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran tidak valid.']);
            }

            $addonTotal = array_sum(array_column($selectedAddons, 'price'));
            $bundleAddonTotal = array_sum(array_column(array_filter($selectedAddons, fn (array $addon): bool => $addon['is_bundle_item']), 'price'));
            $extraAddonTotal = $addonTotal - $bundleAddonTotal;
            $bundlePrice = $bundle ? (int) $bundle->bundle_price : null;
            $discountTotal = $bundle ? max(0, (int) $tier->price + $bundleAddonTotal - $bundlePrice) : 0;
            $paymentStatus = $paymentMethod === 'cash' ? 'paid' : 'unpaid';
            $booking = Booking::create([
                'studio_id' => $studio->id,
                'pricing_tier_id' => $tier->id,
                'bundle_id' => $bundle?->id,
                'bundle_name_at_booking' => $bundle?->name,
                'bundle_price_at_booking' => $bundlePrice,
                'discount_total' => $discountTotal,
                'customer_name' => trim($input['customer_name']),
                'customer_wa' => preg_replace('/\D+/', '', $input['customer_wa']),
                'start_time' => $start,
                'end_time' => $end,
                'duration_minutes' => $tier->duration_minutes,
                'status' => $paymentMethod === 'cash' ? 'confirmed' : 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_expires_at' => $paymentMethod === 'gateway' ? now()->addMinutes(15) : null,
                'source' => 'manual',
                'studio_price_at_booking' => $tier->price,
                'addon_total' => $addonTotal,
                'grand_total' => $bundle ? $bundlePrice + $extraAddonTotal : $tier->price + $addonTotal,
                'payload' => [
                    'studio' => ['id' => $studio->id, 'name' => $studio->name],
                    'pricing_tier' => ['id' => $tier->id, 'duration_minutes' => $tier->duration_minutes, 'price' => $tier->price],
                    'addons' => $selectedAddons,
                    'bundle' => $bundle ? ['id' => $bundle->id, 'name' => $bundle->name, 'price' => $bundlePrice] : null,
                ],
            ]);

            foreach ($selectedAddons as $addon) {
                BookingAddon::create([
                    'booking_id' => $booking->id,
                    'addon_id' => $addon['addon_id'],
                    'resource_id' => $addon['resource_id'],
                    'duration_minutes' => $addon['duration_minutes'],
                    'price_at_booking' => $addon['price'],
                    'is_bundle_item' => $addon['is_bundle_item'],
                ]);
            }

            if ($paymentMethod === 'gateway') {
                $this->paymentGateways->make()->create($booking);
            }

            return $booking->load('studio', 'pricingTier', 'bundle', 'addons.addon', 'addons.resource');
        });
    }

    public function updateManual(array $input): Booking
    {
        return DB::transaction(function () use ($input): Booking {
            $booking = Booking::query()->with('addons')->whereKey($input['id'])->lockForUpdate()->firstOrFail();
            $requestedBundleId = isset($input['bundle_id']) ? (int) $input['bundle_id'] : null;
            $sameBundle = $requestedBundleId !== null && $requestedBundleId === (int) $booking->bundle_id;
            $bundle = null;

            if ($requestedBundleId !== null) {
                $bundleQuery = Bundle::withTrashed()->with([
                    'studio',
                    'pricingTier',
                    'bundleAddons.addon.studios',
                    'bundleAddons.addon.prices',
                ])->whereKey($requestedBundleId);
                if (! $sameBundle) {
                    $bundleQuery->lockForUpdate();
                }
                $bundle = $bundleQuery->firstOrFail();
                if (! $sameBundle && ! $bundle->isSellable()) {
                    throw ValidationException::withMessages([
                        'bundle_id' => 'Bundle sudah tidak tersedia atau kuotanya habis.',
                    ]);
                }
            }

            $studioId = $bundle?->studio_id ?? ($input['studio_id'] ?? null);
            $pricingTierId = $bundle?->pricing_tier_id ?? ($input['pricing_tier_id'] ?? null);
            if (! $studioId || ! $pricingTierId) {
                throw ValidationException::withMessages([
                    'studio_id' => 'Studio dan tier harga wajib dipilih.',
                ]);
            }
            if ($bundle && ((isset($input['studio_id']) && (int) $input['studio_id'] !== (int) $studioId)
                || (isset($input['pricing_tier_id']) && (int) $input['pricing_tier_id'] !== (int) $pricingTierId))) {
                throw ValidationException::withMessages([
                    'bundle_id' => 'Studio dan durasi booking harus mengikuti isi bundle.',
                ]);
            }

            $studio = Studio::query()
                ->whereKey($studioId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $tier = PricingTier::query()
                ->whereKey($pricingTierId)
                ->where('studio_id', $studio->id)
                ->where('is_active', true)
                ->firstOrFail();

            $timezone = config('app.timezone', 'Asia/Jakarta');
            $start = CarbonImmutable::parse($input['start_time'], $timezone);
            $end = $start->addMinutes($tier->duration_minutes);
            $this->validateSchedule($start, $end);

            if ($this->hasOverlap(Booking::query(), 'studio_id', $studio->id, $start, $end, $booking->id)) {
                throw ValidationException::withMessages(['start_time' => 'Studio sudah dibooking pada jam tersebut.']);
            }

            $extraAddons = $input['addons'] ?? [];
            $bundleAddonInputs = $bundle ? $this->bundleAddonInputs($bundle, $sameBundle ? $booking : null) : [];
            $this->assertNoDuplicateBundleAddons($bundleAddonInputs, $extraAddons);
            $selectedAddons = $this->resolveAddons(
                [...$bundleAddonInputs, ...$extraAddons],
                $studio,
                $start,
                $end,
                $tier->duration_minutes,
                $booking->id,
            );

            $addonTotal = array_sum(array_column($selectedAddons, 'price'));
            $bundleAddonTotal = array_sum(array_column(array_filter($selectedAddons, fn (array $addon): bool => $addon['is_bundle_item']), 'price'));
            $extraAddonTotal = $addonTotal - $bundleAddonTotal;
            $bundlePrice = $sameBundle
                ? (int) $booking->bundle_price_at_booking
                : ($bundle ? (int) $bundle->bundle_price : null);
            $discountTotal = $sameBundle
                ? (int) $booking->discount_total
                : ($bundle ? max(0, (int) $tier->price + $bundleAddonTotal - $bundlePrice) : 0);
            $bookingPaymentMethod = $booking->payment_method ?? 'cash';
            $bookingPaymentStatus = $booking->payment_status ?? 'unpaid';
            $existingPayload = is_array($booking->payload) ? $booking->payload : [];
            $payload = array_replace_recursive($existingPayload, [
                'studio' => ['id' => $studio->id, 'name' => $studio->name],
                'pricing_tier' => ['id' => $tier->id, 'duration_minutes' => $tier->duration_minutes, 'price' => $tier->price],
                'addons' => $selectedAddons,
                'bundle' => $bundle ? [
                    'id' => $bundle->id,
                    'name' => $sameBundle ? $booking->bundle_name_at_booking : $bundle->name,
                    'price' => $bundlePrice,
                ] : null,
            ]);

            $booking->update([
                'studio_id' => $studio->id,
                'pricing_tier_id' => $tier->id,
                'bundle_id' => $bundle?->id,
                'bundle_name_at_booking' => $bundle ? ($sameBundle ? $booking->bundle_name_at_booking : $bundle->name) : null,
                'bundle_price_at_booking' => $bundlePrice,
                'discount_total' => $discountTotal,
                'customer_name' => trim($input['customer_name']),
                'customer_wa' => preg_replace('/\D+/', '', $input['customer_wa']),
                'start_time' => $start,
                'end_time' => $end,
                'duration_minutes' => $tier->duration_minutes,
                'status' => $booking->status,
                'payment_method' => $bookingPaymentMethod,
                'payment_status' => $bookingPaymentStatus,
                'payment_expires_at' => $booking->payment_expires_at,
                'studio_price_at_booking' => $sameBundle ? $booking->studio_price_at_booking : $tier->price,
                'addon_total' => $addonTotal,
                'grand_total' => $bundle ? $bundlePrice + $extraAddonTotal : $tier->price + $addonTotal,
                'payload' => $payload,
            ]);

            $booking->addons()->delete();
            foreach ($selectedAddons as $addon) {
                BookingAddon::create([
                    'booking_id' => $booking->id,
                    'addon_id' => $addon['addon_id'],
                    'resource_id' => $addon['resource_id'],
                    'duration_minutes' => $addon['duration_minutes'],
                    'price_at_booking' => $addon['price'],
                    'is_bundle_item' => $addon['is_bundle_item'],
                ]);
            }

            return $booking->refresh()->load('studio', 'pricingTier', 'bundle', 'addons.addon', 'addons.resource');
        });
    }

    public function deleteBooking(int $bookingId): Booking
    {
        return DB::transaction(function () use ($bookingId): Booking {
            $booking = Booking::query()->whereKey($bookingId)->firstOrFail();
            $booking->delete();

            return $booking;
        });
    }

    public function cancelBooking(int $bookingId): Booking
    {
        return DB::transaction(function () use ($bookingId): Booking {
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status === 'cancelled') {
                return $booking->load('studio', 'pricingTier', 'bundle', 'addons.addon', 'addons.resource');
            }

            if (! in_array($booking->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'id' => 'Booking yang sudah kedaluwarsa atau selesai tidak dapat dibatalkan.',
                ]);
            }

            $booking->update(['status' => 'cancelled']);

            return $booking->refresh()->load('studio', 'pricingTier', 'bundle', 'addons.addon', 'addons.resource');
        });
    }

    private function bundleAddonInputs(Bundle $bundle, ?Booking $existingBooking = null): array
    {
        $existingItems = $existingBooking?->addons
            ->where('is_bundle_item', true)
            ->keyBy('addon_id') ?? collect();

        return $bundle->bundleAddons->map(function ($bundleAddon) use ($existingItems): array {
            $existingItem = $existingItems->get($bundleAddon->addon_id);
            return [
                'addon_id' => (int) $bundleAddon->addon_id,
                'duration_minutes' => $bundleAddon->duration_minutes,
                'is_bundle_item' => true,
                'price_at_booking' => $existingItem?->price_at_booking,
            ];
        })->all();
    }

    private function assertNoDuplicateBundleAddons(array $bundleAddons, array $extraAddons): void
    {
        $bundleIds = array_column($bundleAddons, 'addon_id');
        $extraIds = array_column($extraAddons, 'addon_id');
        if (count($extraIds) !== count(array_unique($extraIds)) || array_intersect($bundleIds, $extraIds)) {
            throw ValidationException::withMessages([
                'addons' => 'Add-on ekstra tidak boleh duplikat atau sudah termasuk di dalam bundle.',
            ]);
        }
    }

    private function resolveAddons(array $requested, Studio $studio, CarbonImmutable $start, CarbonImmutable $end, int $studioDuration, ?int $excludeBookingId = null): array
    {
        $resolved = [];
        foreach ($requested as $item) {
            $addon = Addon::query()
                ->whereKey($item['addon_id'])
                ->when(empty($item['is_bundle_item']), fn (Builder $query) => $query->where('is_active', true))
                ->whereHas('studios', fn (Builder $query) => $query->whereKey($studio->id)->where('is_active', true))
                ->with(['prices' => fn ($query) => $query->where('is_active', true), 'resources' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
                ->firstOrFail();
            $duration = $addon->pricing_type === 'flat' ? $studioDuration : (int) ($item['duration_minutes'] ?? 0);
            $masterPrice = $addon->pricing_type === 'flat'
                ? (int) $addon->flat_price
                : (int) AddonPrice::query()->where('addon_id', $addon->id)->where('duration_minutes', $duration)->where('is_active', true)->value('price');
            $price = isset($item['price_at_booking'])
                ? (int) $item['price_at_booking']
                : $masterPrice;
            if ($duration < 1 || $duration > $studioDuration || $price < 0 || ($masterPrice < 0 && ! isset($item['price_at_booking']))) {
                throw ValidationException::withMessages(['addons' => "Durasi addon {$addon->name} tidak tersedia untuk booking ini."]);
            }

            $resources = Resource::query()
                ->whereIn('id', $addon->resources->modelKeys())
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->filter(fn (Resource $resource) => !$this->resourceHasOverlap($resource, $start, $end, $excludeBookingId));
            $resource = $resources->sortBy(fn (Resource $candidate) => [
                $this->resourceBusyMinutes($candidate),
                $candidate->id,
            ])->first();
            if (!$resource) {
                throw ValidationException::withMessages(['addons' => "Addon {$addon->name} tidak memiliki resource yang tersedia pada jam tersebut."]);
            }

            $resolved[] = [
                'addon_id' => $addon->id,
                'addon_name' => $addon->name,
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'duration_minutes' => $duration,
                'price' => $price,
                'is_bundle_item' => (bool) ($item['is_bundle_item'] ?? false),
            ];
        }
        return $resolved;
    }

    private function validateSchedule(CarbonImmutable $start, CarbonImmutable $end): void
    {
        $closingTime = $start->copy()->startOfDay()->setTime(self::CLOSING_HOUR, 0);

        if ($start->minute !== 0 || $start->second !== 0 || $start->hour < self::OPENING_HOUR || $end->greaterThan($closingTime)) {
            throw ValidationException::withMessages(['start_time' => 'Booking harus dimulai setiap satu jam dan berada di jam operasional 10:00-22:00.']);
        }
        if ($start->lessThanOrEqualTo(CarbonImmutable::now($start->getTimezone()))) {
            throw ValidationException::withMessages(['start_time' => 'Waktu booking sudah lewat menurut zona waktu Asia/Jakarta.']);
        }
    }

    private function assertDurationWithinOperatingHours(int $durationMinutes): void
    {
        if ($durationMinutes < 1 || $durationMinutes > (self::CLOSING_HOUR - self::OPENING_HOUR) * 60) {
            throw ValidationException::withMessages(['duration_minutes' => 'Durasi booking tidak valid.']);
        }
    }

    private function activeBookingQuery(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES)->where(function (Builder $nested): void {
            $nested->where('status', '!=', 'pending')->orWhereNull('payment_expires_at')->orWhere('payment_expires_at', '>', now());
        });
    }

    private function hasOverlap(Builder $query, string $column, int $id, CarbonImmutable $start, CarbonImmutable $end, ?int $excludingBookingId = null): bool
    {
        $query = $this->activeBookingQuery($query)
            ->where($column, $id)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);

        if ($excludingBookingId !== null) {
            $query->whereKeyNot($excludingBookingId);
        }

        return $query->exists();
    }

    private function resourceHasOverlap(Resource $resource, CarbonImmutable $start, CarbonImmutable $end, ?int $excludingBookingId = null): bool
    {
        return $resource->bookingAddons()
            ->whereHas('booking', function (Builder $query) use ($start, $end, $excludingBookingId): void {
                $bookingQuery = $this->activeBookingQuery($query)
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);

                if ($excludingBookingId !== null) {
                    $bookingQuery->whereKeyNot($excludingBookingId);
                }
            })
            ->exists();
    }

    private function resourceBusyMinutes(Resource $resource): int
    {
        return (int) $resource->bookingAddons()
            ->whereHas('booking', fn (Builder $query) => $this->activeBookingQuery($query))
            ->sum('duration_minutes');
    }
}
