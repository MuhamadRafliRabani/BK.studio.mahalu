<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msb_ms_studio_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('msb_ms_studios')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msb_ms_studio_photos');
    }
};
