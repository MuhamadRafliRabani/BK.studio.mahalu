<?php

use App\Http\Controllers\BookingReceiptController;
use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/api/payments/callback', PaymentCallbackController::class)
    ->withoutMiddleware(['web']);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/session', function () {
    $user = Auth::guard('web')->user();

    if (! $user) {
        return response()->json(['user' => null], 401);
    }

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
        ],
    ]);
})->middleware('web');

Route::post('/auth/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->noContent();
})->middleware('web');

Route::get('/bookings/{booking}/receipt', [BookingReceiptController::class, 'show'])
    ->middleware('auth')
    ->name('bookings.receipt');
