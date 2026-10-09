<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msb_ms_addons', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('flat_price');
        });

        Schema::table('msb_ms_pricing_tiers', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('price');
        });

        Schema::table('msb_ms_addon_prices', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('price');
        });

        Schema::table('msb_bk_bookings', function (Blueprint $table): void {
            $table->foreignId('pricing_tier_id')->nullable()->after('studio_id')
                ->constrained('msb_ms_pricing_tiers')->nullOnDelete();
            $table->unsignedInteger('duration_minutes')->default(60)->after('end_time');
            $table->unsignedBigInteger('studio_price_at_booking')->default(0)->after('duration_minutes');
            $table->unsignedBigInteger('addon_total')->default(0)->after('studio_price_at_booking');
            $table->unsignedBigInteger('grand_total')->default(0)->after('addon_total');
            $table->json('payload')->nullable()->after('grand_total');
            $table->timestamp('payment_expires_at')->nullable()->after('payment_status');
            $table->string('payment_reference')->nullable()->after('payment_expires_at');
            $table->index(['status', 'start_time', 'end_time']);
        });

        Schema::table('msb_bk_booking_addons', function (Blueprint $table): void {
            $table->unsignedInteger('duration_minutes')->nullable()->after('resource_id');
        });
    }

    public function down(): void
    {
        Schema::table('msb_bk_booking_addons', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });

        Schema::table('msb_bk_bookings', function (Blueprint $table): void {
            $table->dropIndex(['status', 'start_time', 'end_time']);
            $table->dropForeign(['pricing_tier_id']);
            $table->dropColumn([
                'pricing_tier_id', 'duration_minutes', 'studio_price_at_booking',
                'addon_total', 'grand_total', 'payload', 'payment_expires_at',
                'payment_reference',
            ]);
        });

        Schema::table('msb_ms_pricing_tiers', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('msb_ms_addon_prices', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('msb_ms_addons', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};