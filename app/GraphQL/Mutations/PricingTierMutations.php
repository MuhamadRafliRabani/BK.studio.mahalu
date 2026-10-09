<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\PricingTier;
use App\Modules\Master\Models\Bundle;
use Illuminate\Validation\ValidationException;

class PricingTierMutations
{
    public function create($_, array $args): PricingTier
    {
        $this->validate($args);

        return PricingTier::create($args)->load('studio');
    }

    public function update($_, array $args): PricingTier
    {
        $tier = PricingTier::findOrFail($args['id']);
        $this->validate($args);
        $tier->update([
            'duration_minutes' => $args['duration_minutes'],
            'price' => $args['price'],
        ]);

        return $tier->refresh()->load('studio');
    }

    public function delete($_, array $args): PricingTier
    {
        $tier = PricingTier::findOrFail($args['id']);
        $bundle = Bundle::withTrashed()->where('pricing_tier_id', $tier->id)->first();
        if ($bundle) {
            throw ValidationException::withMessages([
                'id' => "Pricing tier tidak dapat dihapus karena dipakai bundle {$bundle->name}.",
            ]);
        }

        $tier->delete();

        return $tier;
    }

    private function validate(array $args): void
    {
        if (($args['duration_minutes'] ?? 0) < 1) {
            throw ValidationException::withMessages([
            'duration_minutes' => ['Durasi harus minimal 1 menit.'],
            ]);
        }

        if (($args['price'] ?? 0) < 0) {
            throw ValidationException::withMessages([
                'price' => ['Harga tidak boleh negatif.'],
            ]);
        }
    }
}
