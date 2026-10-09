<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\Resource;
use Illuminate\Support\Facades\DB;

class ResourceMutations
{
    public function create($_, array $args): Resource
    {
        return Resource::create([
            'addon_id' => $args['addon_id'],
            'name' => trim($args['name']),
            'is_active' => $args['is_active'] ?? true,
            'sort_order' => $args['sort_order'] ?? 0,
        ]);
    }

    public function update($_, array $args): Resource
    {
        $resource = Resource::findOrFail($args['id']);
        $resource->update(array_filter([
            'addon_id' => $args['addon_id'] ?? null,
            'name' => array_key_exists('name', $args) ? trim($args['name']) : null,
            'is_active' => $args['is_active'] ?? null,
            'sort_order' => $args['sort_order'] ?? null,
        ], static fn ($value) => $value !== null));

        return $resource->fresh();
    }

    public function delete($_, array $args): Resource
    {
        $resource = Resource::findOrFail($args['id']);
        $resource->delete();

        return $resource;
    }

    public function reorder($_, array $args): array
    {
        DB::transaction(function () use ($args): void {
            foreach ($args['items'] as $item) {
                Resource::whereKey($item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return Resource::query()->orderBy('sort_order')->get()->all();
    }
}