<?php

namespace App\Modules\Master\Services;

use App\Modules\Master\Models\Addon;
use App\Modules\Master\Models\AddonPrice;
use App\Modules\Master\Models\Bundle;
use App\Modules\Master\Models\BundleAddon;
use App\Modules\Master\Models\PricingTier;
use App\Modules\Master\Models\Studio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BundleService
{
    public function all(): Collection
    {
        return $this->bundleQuery()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function sellable(): Collection
    {
        return $this->all()->filter(fn (Bundle $bundle): bool => $bundle->isSellable())->values();
    }

    public function findSellableForPurchase(int $bundleId): Bundle
    {
        $bundle = $this->bundleQuery()
            ->whereKey($bundleId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $bundle->isSellable()) {
            throw ValidationException::withMessages([
                'bundle_id' => 'Bundle sudah tidak tersedia atau kuotanya habis.',
            ]);
        }

        return $bundle;
    }

    public function find(int $bundleId): Bundle
    {
        return $this->bundleQuery()->whereKey($bundleId)->firstOrFail();
    }

    public function create(array $input): Bundle
    {
        return DB::transaction(function () use ($input): Bundle {
            $data = $this->validatedData($input);
            $bundle = Bundle::create($data['attributes']);
            $this->syncAddons($bundle, $data['addons']);

            return $this->bundleQuery()->whereKey($bundle->id)->firstOrFail();
        });
    }

    public function update(int $bundleId, array $input): Bundle
    {
        return DB::transaction(function () use ($bundleId, $input): Bundle {
            $bundle = Bundle::query()->whereKey($bundleId)->lockForUpdate()->firstOrFail();
            $data = $this->validatedData($input, $bundle);

            if ($bundle->bookings()->exists() && ! $this->sameComposition($bundle, $data)) {
                throw ValidationException::withMessages([
                    'addons' => 'Isi bundle tidak dapat diubah setelah bundle memiliki booking. Buat bundle baru untuk komposisi yang berbeda.',
                ]);
            }

            $bundle->update($data['attributes']);
            if (! $bundle->bookings()->exists()) {
                $this->syncAddons($bundle, $data['addons']);
            }

            return $this->bundleQuery()->whereKey($bundle->id)->firstOrFail();
        });
    }

    public function delete(int $bundleId): Bundle
    {
        $bundle = Bundle::query()->whereKey($bundleId)->firstOrFail();
        $bundle->delete();

        return $bundle;
    }

    public function reorder(array $items): Collection
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                Bundle::query()->whereKey($item['id'])->update([
                    'sort_order' => (int) $item['sort_order'],
                ]);
            }
        });

        return $this->all();
    }

    private function bundleQuery()
    {
        return Bundle::query()->with([
            'studio',
            'pricingTier',
            'bundleAddons.addon.studios',
            'bundleAddons.addon.prices',
        ]);
    }

    /** @return array{attributes: array<string, mixed>, addons: array<int, array{addon_id: int, duration_minutes: ?int}>} */
    private function validatedData(array $input, ?Bundle $bundle = null): array
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'studio_id' => ['required', 'integer', 'exists:msb_ms_studios,id'],
            'pricing_tier_id' => ['required', 'integer', 'exists:msb_ms_pricing_tiers,id'],
            'bundle_price' => ['required', 'integer', 'min:0'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'quota_limit' => ['nullable', 'integer', 'min:1'],
            'quota_period' => ['nullable', Rule::in(['total', 'monthly'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'addons' => ['required', 'array', 'min:1'],
            'addons.*.addon_id' => ['required', 'integer', 'distinct', 'exists:msb_ms_addons,id'],
            'addons.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        if ($data['valid_from'] && $data['valid_until'] && $data['valid_until'] <= $data['valid_from']) {
            throw ValidationException::withMessages([
                'valid_until' => 'Tanggal akhir promo harus setelah tanggal mulai.',
            ]);
        }

        if ($data['quota_limit'] !== null && $data['quota_period'] === null) {
            throw ValidationException::withMessages([
                'quota_period' => 'Periode kuota wajib dipilih jika batas kuota diisi.',
            ]);
        }

        $studio = Studio::query()->whereKey($data['studio_id'])->where('is_active', true)->first();
        $tier = PricingTier::query()
            ->whereKey($data['pricing_tier_id'])
            ->where('studio_id', $data['studio_id'])
            ->where('is_active', true)
            ->first();

        if (! $studio || ! $tier) {
            throw ValidationException::withMessages([
                'pricing_tier_id' => 'Pilih studio aktif dan tier aktif yang dimiliki studio tersebut.',
            ]);
        }

        $resolvedAddons = [];
        $normalPrice = (int) $tier->price;
        foreach ($data['addons'] as $item) {
            $addon = Addon::query()->whereKey($item['addon_id'])->where('is_active', true)->first();
            if (! $addon || ! $addon->studios()->whereKey($studio->id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'addons' => 'Setiap add-on bundle harus aktif dan tersedia di studio yang dipilih.',
                ]);
            }

            if ($addon->pricing_type === 'flat') {
                $duration = null;
                $normalPrice += (int) ($addon->flat_price ?? 0);
            } else {
                $duration = isset($item['duration_minutes']) ? (int) $item['duration_minutes'] : null;
                if ($duration === null || $duration > $tier->duration_minutes) {
                    throw ValidationException::withMessages([
                        'addons' => "Durasi add-on {$addon->name} wajib dipilih dan tidak boleh melebihi durasi Studio.",
                    ]);
                }

                $price = AddonPrice::query()
                    ->where('addon_id', $addon->id)
                    ->where('duration_minutes', $duration)
                    ->where('is_active', true)
                    ->value('price');
                if ($price === null) {
                    throw ValidationException::withMessages([
                        'addons' => "Harga add-on {$addon->name} untuk durasi tersebut tidak tersedia.",
                    ]);
                }
                $normalPrice += (int) $price;
            }

            $resolvedAddons[] = [
                'addon_id' => (int) $addon->id,
                'duration_minutes' => $duration,
            ];
        }

        if ((int) $data['bundle_price'] >= $normalPrice) {
            throw ValidationException::withMessages([
                'bundle_price' => 'Harga bundle harus lebih rendah dari harga normal seluruh isinya.',
            ]);
        }

        $composition = [
            'studio_id' => (int) $studio->id,
            'pricing_tier_id' => (int) $tier->id,
            'addons' => $resolvedAddons,
        ];

        return [
            'attributes' => [
                'name' => trim($data['name']),
                'slug' => $this->uniqueSlug($data['name'], $bundle),
                'description' => $data['description'] ?? null,
                'studio_id' => $composition['studio_id'],
                'pricing_tier_id' => $composition['pricing_tier_id'],
                'bundle_price' => (int) $data['bundle_price'],
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'quota_limit' => isset($data['quota_limit']) ? (int) $data['quota_limit'] : null,
                'quota_period' => isset($data['quota_limit']) ? $data['quota_period'] : null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ],
            'addons' => $composition['addons'],
        ];
    }

    private function syncAddons(Bundle $bundle, array $addons): void
    {
        $bundle->bundleAddons()->delete();
        foreach ($addons as $item) {
            $bundle->bundleAddons()->create($item);
        }
    }

    private function sameComposition(Bundle $bundle, array $data): bool
    {
        $currentAddons = $bundle->bundleAddons()
            ->orderBy('addon_id')
            ->get(['addon_id', 'duration_minutes'])
            ->map(fn (BundleAddon $item): array => [
                'addon_id' => (int) $item->addon_id,
                'duration_minutes' => $item->duration_minutes,
            ])->all();
        $nextAddons = collect($data['addons'])
            ->sortBy('addon_id')
            ->values()
            ->all();

        return (int) $bundle->studio_id === $data['attributes']['studio_id']
            && (int) $bundle->pricing_tier_id === $data['attributes']['pricing_tier_id']
            && $currentAddons === $nextAddons;
    }

    private function uniqueSlug(string $name, ?Bundle $bundle = null): string
    {
        $base = Str::slug($name) ?: 'bundle';
        $slug = $base;
        $suffix = 2;

        while (Bundle::query()->where('slug', $slug)->when($bundle, fn ($query) => $query->whereKeyNot($bundle->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
