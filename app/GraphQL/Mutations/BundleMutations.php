<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\Bundle;
use App\Modules\Master\Services\BundleService;
use Illuminate\Database\Eloquent\Collection;

class BundleMutations
{
    public function __construct(private readonly BundleService $bundleService)
    {
    }

    public function all($_, array $args): Collection
    {
        return $this->bundleService->all();
    }

    public function create($_, array $args): Bundle
    {
        return $this->bundleService->create($args['input']);
    }

    public function update($_, array $args): Bundle
    {
        return $this->bundleService->update((int) $args['id'], $args['input']);
    }

    public function delete($_, array $args): Bundle
    {
        return $this->bundleService->delete((int) $args['id']);
    }

    public function reorder($_, array $args): Collection
    {
        return $this->bundleService->reorder($args['items']);
    }
}
