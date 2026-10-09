<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msb_ms_studios', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->unsignedInteger('capacity')->nullable()->after('description');
            $table->decimal('size_sqm', 8, 2)->nullable()->after('capacity');
            $table->string('thumbnail')->nullable()->after('size_sqm');
            $table->string('slug')->nullable()->unique()->after('thumbnail');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('msb_ms_studios', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'description',
                'capacity',
                'size_sqm',
                'thumbnail',
                'slug',
                'sort_order',
            ]);
        });
    }
};
