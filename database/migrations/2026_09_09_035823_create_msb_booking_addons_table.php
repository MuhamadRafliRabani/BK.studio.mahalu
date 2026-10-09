<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('msb_booking_addons', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')->constrained('msb_bookings')->cascadeOnDelete();
        $table->foreignId('addon_id')->constrained('msb_addons');
        $table->foreignId('resource_id')->nullable()->constrained('msb_resources');
        $table->unsignedBigInteger('price_at_booking'); // snapshot harga saat booking dibuat
        $table->auditColumns();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_booking_addons');
    }
};
