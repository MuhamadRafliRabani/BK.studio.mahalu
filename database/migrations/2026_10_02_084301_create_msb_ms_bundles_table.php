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
        Schema::create('msb_ms_bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->foreignId('studio_id')->constrained('msb_ms_studios')->restrictOnDelete();
            $table->foreignId('pricing_tier_id')->constrained('msb_ms_pricing_tiers')->restrictOnDelete();
            $table->unsignedBigInteger('bundle_price');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('quota_limit')->nullable();
            $table->string('quota_period')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->softDeletes();
            $table->index(['is_active', 'valid_from', 'valid_until']);
        });

        Schema::create('msb_ms_bundle_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_id')->constrained('msb_ms_bundles')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('msb_ms_addons')->restrictOnDelete();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->auditColumns();
            $table->unique(['bundle_id', 'addon_id']);
        });

        Schema::table('msb_bk_bookings', function (Blueprint $table): void {
            $table->foreignId('bundle_id')->nullable()->constrained('msb_ms_bundles')->restrictOnDelete();
            $table->string('bundle_name_at_booking')->nullable();
            $table->unsignedBigInteger('bundle_price_at_booking')->nullable();
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->index(['bundle_id', 'status', 'cretime']);
        });

        Schema::table('msb_bk_booking_addons', function (Blueprint $table): void {
            $table->boolean('is_bundle_item')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msb_bk_booking_addons', function (Blueprint $table): void {
            $table->dropColumn('is_bundle_item');
        });

        Schema::table('msb_bk_bookings', function (Blueprint $table): void {
            $table->dropIndex(['bundle_id', 'status', 'cretime']);
            $table->dropForeign(['bundle_id']);
            $table->dropColumn([
                'bundle_id',
                'bundle_name_at_booking',
                'bundle_price_at_booking',
                'discount_total',
            ]);
        });

        Schema::dropIfExists('msb_ms_bundle_addons');
        Schema::dropIfExists('msb_ms_bundles');
    }
};
