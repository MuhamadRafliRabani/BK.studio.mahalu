<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msb_ms_addon_prices', function (Blueprint $table): void {
            $table->unique(['addon_id', 'duration_hours'], 'addon_prices_addon_duration_unique');
        });
    }

    public function down(): void
    {
        Schema::table('msb_ms_addon_prices', function (Blueprint $table): void {
            $table->dropUnique('addon_prices_addon_duration_unique');
        });
    }
};