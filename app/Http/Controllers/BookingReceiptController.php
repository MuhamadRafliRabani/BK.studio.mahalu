<?php

namespace App\Http\Controllers;

use App\Modules\Booking\Models\Booking;
use Illuminate\View\View;

class BookingReceiptController extends Controller
{
    public function show(Booking $booking): View
    {
        $booking->loadMissing([
            'studio',
            'pricingTier',
            'addons.addon',
            'addons.resource',
        ]);

        return view('Kuitansi.kuitansi', [
            'booking' => $booking,
            'business' => config('receipt'),
        ]);
    }
}
