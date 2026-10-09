<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\Studio;
use Illuminate\Support\Facades\DB;

class ReorderStudios
{
    public function __invoke($_, array $args): array
    {
        DB::transaction(function () use ($args): void {
            foreach ($args['items'] as $item) {
                Studio::whereKey($item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return Studio::query()
            ->orderBy('sort_order')
            ->get()
            ->all();
    }
}
