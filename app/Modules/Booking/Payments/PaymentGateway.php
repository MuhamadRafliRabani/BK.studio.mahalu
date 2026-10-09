<?php

namespace App\Modules\Booking\Payments;

use App\Modules\Booking\Models\Booking;

interface PaymentGateway
{
    /** @return array{reference: string, url: string} */
    public function create(Booking $booking): array;

    public function  handleCallback(array $payload, string $rawBody, string $signature, ?string $externalId = null, ?string $timestamp = null): void;
}
