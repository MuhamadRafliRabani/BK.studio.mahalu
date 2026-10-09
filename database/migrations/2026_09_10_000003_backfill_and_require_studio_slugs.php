<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('msb_ms_studios')
            ->whereNull('slug')
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->each(function (object $studio): void {
                $base = Str::slug($studio->name ?: $studio->code) ?: 'studio';
                $slug = $base;
                $suffix = 2;

                while (DB::table('msb_ms_studios')->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$suffix++;
                }

                DB::table('msb_ms_studios')
                    ->where('id', $studio->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('msb_ms_studios', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('msb_ms_studios', function (Blueprint $table): void {
            $table->string('slug')->nullable()->change();
        });
    }
};
