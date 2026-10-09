<?php

namespace App\GraphQL\Queries;

use App\Modules\Booking\Services\BookingService;
use App\Modules\Master\Models\Addon;
use Illuminate\Database\Eloquent\Builder;

class BookingAddonAvailability
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function __invoke($_, array $args): array
    {
        $addons = Addon::query()
            ->where('is_active', true)
            ->whereHas('studios', fn (Builder $query) => $query->whereKey($args['studio_id'])->where('is_active', true))
            ->get(['id']);

        return $addons->map(fn (Addon $addon) => $this->bookingService->addonAvailability(
            (int) $args['studio_id'],
            $addon->id,
            $args['start_time'],
            (int) $args['duration_minutes'],
        ))->all();
    }
}
