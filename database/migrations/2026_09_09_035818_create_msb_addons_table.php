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
    Schema::create('msb_addons', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // Fotografer, Videografer, MUA, Stylist
        $table->enum('pricing_type', ['per_tier', 'flat']);
        $table->unsignedBigInteger('flat_price')->nullable(); // dipakai kalau pricing_type = flat
        $table->auditColumns();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_addons');
    }
};
