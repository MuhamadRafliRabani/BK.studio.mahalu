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
    Schema::create('msb_bookings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('studio_id')->constrained('msb_studios');
        $table->string('code')->unique(); // "MH-20230909-0001"
        $table->string('customer_name');
        $table->string('customer_wa');
        $table->dateTime('start_time');
        $table->dateTime('end_time');
        $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
        $table->enum('payment_method', ['cash', 'gateway'])->nullable();
        $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
        $table->enum('source', ['self_service', 'manual']);
        $table->auditColumns();

        $table->index(['studio_id', 'code', 'start_time', 'end_time']); // krusial untuk query availability
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_bookings');
    }
};
