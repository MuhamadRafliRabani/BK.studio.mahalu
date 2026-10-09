<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msb_ms_addons', function (Blueprint $table): void {
            $table->string('icon', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('msb_ms_addons', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
