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
    Schema::create('msb_addon_prices', function (Blueprint $table) {
        $table->id();
        $table->foreignId('addon_id')->constrained('msb_addons')->cascadeOnDelete();
        $table->unsignedInteger('duration_hours');
        $table->unsignedBigInteger('price');
        $table->auditColumns();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_addon_prices');
    }
};
