<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msb_bk_bookings', function (Blueprint $table): void {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('msb_bk_bookings', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending')->change();
        });
    }
};
