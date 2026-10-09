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
    Schema::create('msb_pricing_tiers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('studio_id')->constrained('msb_studios')->cascadeOnDelete();
        $table->unsignedInteger('duration_hours');
        $table->unsignedBigInteger('price'); // simpan dalam rupiah (integer), hindari float untuk uang
        $table->auditColumns();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_pricing_tiers');
    }
};
