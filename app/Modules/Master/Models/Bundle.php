<?php

namespace App\Modules\Master\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasGeneratedCode;
use App\Modules\Booking\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bundle extends Model
{
    use HasAuditColumns, HasFactory, HasGeneratedCode, SoftDeletes;

    public const CREATED_AT = 'cretime';
    public const UPDATED_AT = 'modtime';

    protected $table = 'msb_ms_bundles';

    protected $fillable = [
        'name', 'slug', 'description', 'thumbnail', 'studio_id', 'pricing_tier_id',
        'bundle_price', 'valid_from', 'valid_until', 'quota_limit', 'quota_period',
        'sort_order', 'is_active', 'code',
    ];

    protected $casts = [
        'bundle_price' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'quota_limit' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = ['normal_price', 'discount_amount', 'quota_remaining', 'is_sellable'];

    protected static function codePrefix(): string
    {
        return 'BND';
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function pricingTier(): BelongsTo
    {
        return $this->belongsTo(PricingTier::class);
    }

    public function bundleAddons(): HasMany
    {
        return $this->hasMany(BundleAddon::class);
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'msb_ms_bundle_addons')
            ->withPivot(['duration_minutes']);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function normalPrice(): int
    {
        $this->loadMissing(['pricingTier', 'bundleAddons.addon.prices']);
        $total = (int) ($this->pricingTier?->price ?? 0);

        foreach ($this->bundleAddons as $bundleAddon) {
            $addon = $bundleAddon->addon;
            if (! $addon) {
                continue;
            }

            if ($addon->pricing_type === 'flat') {
                $total += (int) ($addon->flat_price ?? 0);
                continue;
            }

            $price = $addon->prices->first(
                fn (AddonPrice $addonPrice): bool => $addonPrice->duration_minutes === $bundleAddon->duration_minutes
                    && $addonPrice->is_active,
            );
            $total += (int) ($price?->price ?? 0);
        }

        return $total;
    }

    public function discountAmount(): int
    {
        return max(0, $this->normalPrice() - (int) $this->bundle_price);
    }

    public function quotaUsed(?int $excludeBookingId = null, ?CarbonImmutable $at = null): int
    {
        if ($this->quota_limit === null) {
            return 0;
        }

        $at ??= CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));
        $query = $this->bookings()
            ->where(function (Builder $bookingQuery) use ($at): void {
                $bookingQuery->whereIn('status', ['confirmed', 'completed'])
                    ->orWhere(function (Builder $pendingQuery) use ($at): void {
                        $pendingQuery->where('status', 'pending')
                            ->where(function (Builder $expiryQuery) use ($at): void {
                                $expiryQuery->whereNull('payment_expires_at')
                                    ->orWhere('payment_expires_at', '>', $at);
                            });
                    });
            });

        if ($this->quota_period === 'monthly') {
            $query->whereBetween('cretime', [$at->startOfMonth(), $at->endOfMonth()]);
        }

        if ($excludeBookingId !== null) {
            $query->whereKeyNot($excludeBookingId);
        }

        return $query->count();
    }

    public function quotaRemaining(): ?int
    {
        return $this->quota_limit === null
            ? null
            : max(0, $this->quota_limit - $this->quotaUsed());
    }

    public function isSellable(?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));
        $this->loadMissing(['studio', 'pricingTier', 'bundleAddons.addon.studios', 'bundleAddons.addon.prices']);

        if (! $this->is_active || ! $this->studio?->is_active || ! $this->pricingTier?->is_active) {
            return false;
        }

        if ($this->pricingTier->studio_id !== $this->studio_id || $this->bundleAddons->isEmpty()) {
            return false;
        }

        if ($this->valid_from && $at->lt($this->valid_from->startOfDay())) {
            return false;
        }

        if ($this->valid_until && $at->gt($this->valid_until->endOfDay())) {
            return false;
        }

        foreach ($this->bundleAddons as $bundleAddon) {
            $addon = $bundleAddon->addon;
            if (! $addon?->is_active || ! $addon->studios->contains('id', $this->studio_id)) {
                return false;
            }

            if ($addon->pricing_type === 'per_tier') {
                $priceExists = $addon->prices->contains(
                    fn (AddonPrice $addonPrice): bool => $addonPrice->duration_minutes === $bundleAddon->duration_minutes
                        && $addonPrice->duration_minutes <= $this->pricingTier->duration_minutes
                        && $addonPrice->is_active,
                );
                if (! $priceExists) {
                    return false;
                }
            }
        }

        if ($this->discountAmount() <= 0) {
            return false;
        }

        return $this->quota_limit === null || $this->quotaUsed(at: $at) < $this->quota_limit;
    }

    public function getNormalPriceAttribute(): int
    {
        return $this->normalPrice();
    }

    public function getDiscountAmountAttribute(): int
    {
        return $this->discountAmount();
    }

    public function getQuotaRemainingAttribute(): ?int
    {
        return $this->quotaRemaining();
    }

    public function getIsSellableAttribute(): bool
    {
        return $this->isSellable();
    }
}
