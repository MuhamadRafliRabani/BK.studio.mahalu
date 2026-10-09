<?php

namespace App\GraphQL\Queries;

use App\Modules\Master\Services\BundleService;
use Illuminate\Database\Eloquent\Collection;

class SellableBundles
{
    public function __construct(private readonly BundleService $bundleService)
    {
    }

    public function __invoke($_, array $args): Collection
    {
        return $this->bundleService->sellable();
    }
}
