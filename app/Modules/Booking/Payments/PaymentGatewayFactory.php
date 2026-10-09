<?php

namespace App\Modules\Booking\Payments;

use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function __construct(private readonly IpaymuGateway $ipaymu)
    {
    }

    public function make(): PaymentGateway
    {
        return match (config('payment.gateway', 'ipy')) {
            'ipy' => $this->ipaymu,
            'mds' => throw new InvalidArgumentException('Gateway Midtrans belum diaktifkan.'),
            default => throw new InvalidArgumentException('Payment gateway tidak didukung.'),
        };
    }
}
