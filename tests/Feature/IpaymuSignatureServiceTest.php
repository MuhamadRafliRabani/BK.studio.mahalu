<?php

namespace Tests\Feature;

use App\Modules\Master\Reservation\Services\IpaymuSignatureService;
use Tests\TestCase;

class IpaymuSignatureServiceTest extends TestCase
{
    public function test_callback_signature_accepts_current_ipaymu_headers(): void
    {
        config()->set('ipaymu.va', '0000005695703592');
        config()->set('ipaymu.api_key', 'SANDBOXD9011739-62CF-4C4B-98B1-842F1A1F4855');

        $payload = [
            'trx_id' => 232957,
            'sid' => '40d9d7e2-222c-4abd-9296-b864f2835d84',
            'reference_id' => 'SBK-DBUMPY',
            'status' => 'berhasil',
            'status_code' => 1,
            'sub_total' => '1250000',
            'total' => '1272500',
            'amount' => '1272500',
            'fee' => '26000',
            'paid_off' => 1246500,
            'created_at' => '2026-09-17 20:32:50',
            'expired_at' => '2026-09-18 11:32:50',
            'paid_at' => '2026-09-17 20:32:59',
            'settlement_status' => 'settled',
            'transaction_status_code' => 7,
            'is_escrow' => true,
            'system_notes' => 'Sandbox notify',
            'via' => 'va',
            'channel' => 'btn',
            'payment_no' => '98166108078',
            'buyer_name' => 'Lala Development',
            'buyer_email' => 'developmentlala@gmail.com',
            'buyer_phone' => '085767586577',
            'additional_info' => [],
            'url' => 'https://throwing-winnings-movable.ngrok-free.dev/api/payments/callback',
            'va' => '98166108078',
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $externalId = '20260917203954792240';
        $timestamp = '2026-09-17T20:39:54+07:00';
        $hashBody = strtolower(hash('sha256', $rawBody));
        $stringToSign = strtoupper('POST') . ':' . config('ipaymu.va') . ':' . $hashBody . ':' . config('ipaymu.api_key') . ':' . $externalId . ':' . $timestamp;
        $signature = hash_hmac('sha256', $stringToSign, config('ipaymu.api_key'));

        $service = app(IpaymuSignatureService::class);

        $this->assertTrue($service->validateCallback($payload, $signature, $rawBody, $externalId, $timestamp));
    }

    public function test_callback_signature_accepts_real_ipaymu_callback_payload_using_va_secret(): void
    {
        config()->set('ipaymu.va', '0000005695703592');
        config()->set('ipaymu.api_key', 'SANDBOXD9011739-62CF-4C4B-98B1-842F1A1F4855');

        $payload = [
            'trx_id' => 232961,
            'sid' => 'c0dface4-766b-42e2-b11b-595057a0048d',
            'reference_id' => 'SBK-RWSXPL',
            'status' => 'berhasil',
            'status_code' => 1,
            'sub_total' => '3750000',
            'total' => '3817500',
            'amount' => '3817500',
            'fee' => '71000',
            'paid_off' => 3746500,
            'created_at' => '2026-09-17 20:51:42',
            'expired_at' => '2026-09-18 11:51:42',
            'paid_at' => '2026-09-17 20:51:48',
            'settlement_status' => 'settled',
            'transaction_status_code' => 7,
            'is_escrow' => true,
            'system_notes' => 'Sandbox notify',
            'via' => 'va',
            'channel' => 'cimb',
            'payment_no' => '000045111009',
            'buyer_name' => 'Lala Development',
            'buyer_email' => 'developmentlala@gmail.com',
            'buyer_phone' => '08562342343',
            'additional_info' => [],
            'url' => 'https://throwing-winnings-movable.ngrok-free.dev/api/payments/callback',
            'va' => '000045111009',
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = 'aad6094fa192e6a2d30e91da1fa7b94c3447c84b2c6a9cc0e4f9a42cce237aef';
        $externalId = '20260917205148985971';
        $timestamp = '2026-09-17T20:51:48+07:00';

        $service = app(IpaymuSignatureService::class);

        $this->assertTrue($service->validateCallback($payload, $signature, $rawBody, $externalId, $timestamp));
    }
}
