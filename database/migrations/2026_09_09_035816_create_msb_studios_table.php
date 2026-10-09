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
    Schema::create('msb_studios', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // "MH STUDIO 01", "MH STUDIO 02"
        $table->string('code')->unique(); // "MH STUDIO 01", "MH STUDIO 02"
        $table->boolean('is_active')->default(true);
        $table->auditColumns();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msb_studios');
    }
};
