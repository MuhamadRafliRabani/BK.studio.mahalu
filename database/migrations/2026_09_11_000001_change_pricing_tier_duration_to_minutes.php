<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msb_ms_pricing_tiers', function (Blueprint $table): void {
            $table->unsignedInteger('duration_minutes')->default(60)->after('studio_id');
        });

        DB::table('msb_ms_pricing_tiers')->update([
            'duration_minutes' => DB::raw('duration_hours * 60'),
        ]);

        Schema::table('msb_ms_pricing_tiers', function (Blueprint $table): void {
            $table->dropColumn('duration_hours');
        });
    }

    public function down(): void
    {
        Schema::table('msb_ms_pricing_tiers', function (Blueprint $table): void {
            $table->unsignedInteger('duration_hours')->default(1)->after('studio_id');
        });

        DB::table('msb_ms_pricing_tiers')->update([
            'duration_hours' => DB::raw('CEIL(duration_minutes / 60)'),
        ]);

        Schema::table('msb_ms_pricing_tiers', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });
    }
};
