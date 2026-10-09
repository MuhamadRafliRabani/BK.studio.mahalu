<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\AddonPrice;
use App\Modules\Master\Models\BundleAddon;
use Illuminate\Validation\ValidationException;

class AddonPriceMutations
{
    public function upsert($_, array $args): AddonPrice
    {
        return AddonPrice::updateOrCreate(
            [
                'addon_id' => $args['addon_id'],
                'duration_minutes' => $args['duration_minutes'],
            ],
            ['price' => $args['price']],
        );
    }

    public function delete($_, array $args): AddonPrice
    {
        $price = AddonPrice::findOrFail($args['id']);
        $bundleAddon = BundleAddon::query()
            ->where('addon_id', $price->addon_id)
            ->where('duration_minutes', $price->duration_minutes)
            ->with(['bundle' => fn ($query) => $query->withTrashed()])
            ->first();
        if ($bundleAddon) {
            throw ValidationException::withMessages([
                'id' => "Harga add-on tidak dapat dihapus karena dipakai bundle {$bundleAddon->bundle?->name}.",
            ]);
        }

        $price->delete();

        return $price;
    }
}