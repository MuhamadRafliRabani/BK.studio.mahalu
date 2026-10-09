<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\Addon;
use App\Modules\Master\Models\Bundle;
use App\Modules\Master\Models\BundleAddon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddonMutations
{
    public function create($_, array $args): Addon
    {
        return DB::transaction(function () use ($args): Addon {
            $addon = Addon::create([
                'name' => trim($args['name']),
                'icon' => $args['icon'],
                'pricing_type' => $args['pricing_type'],
                'flat_price' => $args['pricing_type'] === 'flat' ? ($args['flat_price'] ?? null) : null,
            ]);
            $addon->studios()->sync($args['studio_ids'] ?? []);

            return $addon->load('studios', 'prices');
        });
    }

    public function update($_, array $args): Addon
    {
        return DB::transaction(function () use ($args): Addon {
            $addon = Addon::findOrFail($args['id']);
            $pricingType = $args['pricing_type'] ?? $addon->pricing_type;
            if ($pricingType === 'flat') {
                $bundleAddon = BundleAddon::query()
                    ->where('addon_id', $addon->id)
                    ->whereNotNull('duration_minutes')
                    ->with(['bundle' => fn ($query) => $query->withTrashed()])
                    ->first();
                if ($bundleAddon) {
                    throw ValidationException::withMessages([
                        'pricing_type' => "Pola harga tidak dapat diubah karena add-on dipakai bundle {$bundleAddon->bundle?->name}.",
                    ]);
                }
            }

            if (array_key_exists('studio_ids', $args)) {
                $studioIds = array_map('intval', $args['studio_ids'] ?? []);
                $bundleAddon = BundleAddon::query()
                    ->where('addon_id', $addon->id)
                    ->whereHas('bundle', function ($query) use ($studioIds): void {
                        $query->withTrashed();
                        if ($studioIds !== []) {
                            $query->whereNotIn('studio_id', $studioIds);
                        }
                    })
                    ->with(['bundle' => fn ($query) => $query->withTrashed()])
                    ->first();
                if ($bundleAddon) {
                    throw ValidationException::withMessages([
                        'studio_ids' => "Studio tidak dapat dilepas karena add-on dipakai bundle {$bundleAddon->bundle?->name}.",
                    ]);
                }
            }

            $addon->update([
                'name' => array_key_exists('name', $args) ? trim($args['name']) : $addon->name,
                'icon' => array_key_exists('icon', $args) ? $args['icon'] : $addon->icon,
                'pricing_type' => $pricingType,
                'flat_price' => $pricingType === 'flat' ? ($args['flat_price'] ?? null) : null,
            ]);
            if ($pricingType === 'flat') {
                $addon->prices()->delete();
            }
            if (array_key_exists('studio_ids', $args)) {
                $addon->studios()->sync($args['studio_ids'] ?? []);
            }

            return $addon->fresh()->load('studios', 'prices');
        });
    }

    public function delete($_, array $args): Addon
    {
        $addon = Addon::findOrFail($args['id']);
        $bundleAddon = BundleAddon::query()
            ->where('addon_id', $addon->id)
            ->with(['bundle' => fn ($query) => $query->withTrashed()])
            ->first();
        if ($bundleAddon) {
            throw ValidationException::withMessages([
                'id' => "Add-on tidak dapat dihapus karena dipakai bundle {$bundleAddon->bundle?->name}.",
            ]);
        }

        $addon->delete();

        return $addon;
    }
}