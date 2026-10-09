<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\Addon;
use App\Modules\Master\Models\BundleAddon;
use Illuminate\Validation\ValidationException;

class SyncAddonStudios
{
    public function __invoke($_, array $args)
    {
        $addon = Addon::findOrFail($args['addon_id']);
        $studioIds = array_map('intval', $args['studio_ids']);
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

        $addon->studios()->sync($args['studio_ids']); // otomatis insert/delete pivot yang perlu

        return $addon->load('studios');
    }
}