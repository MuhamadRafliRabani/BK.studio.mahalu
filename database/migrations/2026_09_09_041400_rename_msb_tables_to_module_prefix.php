<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapping: [nama_lama => nama_baru]
     */
    protected array $renames = [
        'msb_studios'             => 'msb_ms_studios',
        'msb_pricing_tiers'       => 'msb_ms_pricing_tiers',
        'msb_addons'              => 'msb_ms_addons',
        'msb_addon_studios'       => 'msb_ms_addon_studios',
        'msb_addon_prices'        => 'msb_ms_addon_prices',
        'msb_resources'           => 'msb_ms_resources',
        'msb_bookings'            => 'msb_bk_bookings',
        'msb_booking_addons'      => 'msb_bk_booking_addons',
        'msb_reschedule_requests' => 'msb_bk_reschedule_requests',
    ];

    public function up(): void
    {
        foreach ($this->renames as $old => $new) {
            if (Schema::hasTable($old) && !Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->renames) as $old => $new) {
            if (Schema::hasTable($new) && !Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
};