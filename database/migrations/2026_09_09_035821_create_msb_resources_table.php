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
    Schema::create('msb_resources', function (Blueprint $table) {
        $table->id();
        $table->foreignId('addon_id')->constrained('msb_addons')->cascadeOnDelete();
        $table->string('name'); // nama fotografer/videografer/MUA/stylist
        $table->string('code')->unique(); // kode unik untuk fotografer/videografer/MUA/stylist
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->auditColumns();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_resources');
    }
};
