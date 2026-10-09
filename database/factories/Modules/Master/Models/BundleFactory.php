<?php

namespace Database\Factories\Modules\Master\Models;

use App\Modules\Master\Models\Bundle;
use App\Modules\Master\Models\Studio;
use App\Modules\Master\Models\PricingTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'studio_id' => Studio::factory(),
            'pricing_tier_id' => PricingTier::factory(),
            'bundle_price' => fake()->numberBetween(50000, 500000),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
