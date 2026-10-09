<?php

namespace App\Modules\Booking\Payments;

use App\Modules\Booking\Models\Booking;
use App\Modules\Master\Reservation\Services\IpaymuSignatureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class IpaymuGateway implements PaymentGateway
{
    public function __construct(private readonly IpaymuSignatureService $signature)
    {
    }

    public function create(Booking $booking): array
    {
        $reference = $booking->code ?: 'booking-'.Str::lower(Str::random(10));
        $body = [
            'product' => [$this->productName($booking)],
            'qty' => [1],
            'price' => [$booking->grand_total],
            'returnUrl' => $this->url('return_url', $reference),
            'cancelUrl' => $this->url('cancel_url', $reference),
            'notifyUrl' => (string) config('ipaymu.notify_url'),
            'referenceId' => $reference,
            'expired' => (int) config('ipaymu.expiry_minutes', 15),
        ];

        $response = Http::acceptJson()
            ->withHeaders([
                'va' => (string) config('ipaymu.va'),
                'signature' => $this->signature->generate($body),
                'timestamp' => now()->format('YmdHis'),
            ])
            ->post(rtrim((string) config('ipaymu.base_url'), '/').'/api/v2/payment', $body)
            ->throw()
            ->json();

        $data = $response['Data'] ?? $response['data'] ?? [];
        $url = $data['Url'] ?? $data['url'] ?? null;
        $sessionId = $data['SessionId'] ?? $data['sessionId'] ?? null;
        if (! $url) {
            throw new RuntimeException('iPaymu tidak mengembalikan URL pembayaran.');
        }

        $booking->update([
            'payment_reference' => $sessionId ?: $reference,
            'payload' => array_merge($booking->payload ?? [], [
                'payment_gateway' => 'ipy',
                'payment_url' => $url,
                'ipaymu_session_id' => $sessionId,
            ]),
        ]);

        return ['reference' => $sessionId ?: $reference, 'url' => $url];
    }

    public function handleCallback(array $payload, string $rawBody, string $signature, ?string $externalId = null, ?string $timestamp = null): void
    {
        if (! $this->signature->validateCallback($payload, $signature, $rawBody, $externalId, $timestamp)) {
            throw new RuntimeException('Signature callback iPaymu tidak valid.');
        }

        $reference = $payload['reference_id'] ?? $payload['referenceId'] ?? null;
        if (! $reference) {
            throw new RuntimeException('Referensi booking tidak ditemukan.');
        }

        $statusCode = (string) ($payload['status_code'] ?? $payload['statusCode'] ?? '');

        DB::transaction(function () use ($reference, $statusCode): void {
            $booking = Booking::query()
                ->where(function ($query) use ($reference): void {
                    $query->where('payment_reference', $reference)
                        ->orWhere('code', $reference);
                })
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status === 'cancelled') {
                if ($statusCode === '1' && $booking->payment_status !== 'paid') {
                    $booking->update(['payment_status' => 'paid']);
                }

                return;
            }

            $booking->update(match ($statusCode) {
                '1' => ['payment_status' => 'paid', 'status' => 'confirmed'],
                '-2' => ['payment_status' => 'unpaid', 'status' => 'expired'],
                '-1' => ['payment_status' => 'unpaid', 'status' => 'cancelled'],
                default => ['payment_status' => 'unpaid'],
            });
        });
    }

    private function productName(Booking $booking): string
    {
        return sprintf('Booking %s - %s', $booking->code, $booking->studio?->name ?? 'Studio');
    }

    private function url(string $key, string $reference): string
    {
        return str_replace('{referenceId}', urlencode($reference), (string) config("ipaymu.{$key}"));
    }
}
