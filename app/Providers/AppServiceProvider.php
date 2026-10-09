<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blueprint::macro('auditColumns', function () {
            /** @var Blueprint $this */
            $this->timestamp('cretime')->nullable();
            $this->foreignId('creby')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamp('modtime')->nullable();
            $this->foreignId('modby')->nullable()->constrained('users')->nullOnDelete();
        });
    }
}