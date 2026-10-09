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
    Schema::create('msb_reschedule_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')->constrained('msb_bookings')->cascadeOnDelete();
        $table->dateTime('old_start_time');
        $table->dateTime('old_end_time');
        $table->dateTime('new_start_time');
        $table->dateTime('new_end_time');
        $table->enum('status', ['pending', 'confirmed', 'auto_cancelled'])->default('pending');
        $table->foreignId('requested_by')->constrained('users'); // tabel users TIDAK pakai prefix msb_ (lihat catatan di bawah)
        $table->dateTime('confirmation_deadline'); // waktu jadwal - 24 jam
        $table->auditColumns();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_reschedule_requests');
    }
};
