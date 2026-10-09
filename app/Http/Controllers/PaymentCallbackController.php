<?php

namespace App\Http\Controllers;

use App\Modules\Booking\Payments\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayFactory $gateways)
    {
        try {
            $gateways->make()->handleCallback(
                $request->all(),
                $request->getContent(),
                (string) $request->header('X-Signature', $request->input('signature', '')),
                (string) $request->header('X-External-ID', ''),
                (string) $request->header('X-Timestamp', ''),
            );
        } catch (\Throwable $exception) {
            Log::warning('Payment callback rejected.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid payment callback.'], 400);
        }

        return response()->json(['ok' => true]);
    }
}
