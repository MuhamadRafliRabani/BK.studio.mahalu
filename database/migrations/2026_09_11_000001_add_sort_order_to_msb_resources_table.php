<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('msb_ms_resources', 'sort_order')) {
            Schema::table('msb_ms_resources', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->default(0)->after('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('msb_ms_resources', 'sort_order')) {
            Schema::table('msb_ms_resources', function (Blueprint $table): void {
                $table->dropColumn('sort_order');
            });
        }
    }
};