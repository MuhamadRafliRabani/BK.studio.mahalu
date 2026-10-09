<?php

namespace App\Modules\Master\Reservation\Services;

use Illuminate\Support\Facades\Log;

class IpaymuSignatureService
{
    public function generate(array $body): string
    {
        $va = (string) config('ipaymu.va');
        $apiKey = (string) config('ipaymu.api_key');
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hashedBody = strtolower(hash('sha256', (string) $json));

        return hash_hmac('sha256', "POST:{$va}:{$hashedBody}:{$apiKey}", $apiKey);
    }

    public function validateCallback(array $payload, ?string $received = null, ?string $rawBody = null, ?string $externalId = null, ?string $timestamp = null): bool
    {
        $received = trim((string) ($received ?? ($payload['signature'] ?? '')));
        if ($received === '') {
            return false;
        }

        $va = (string) config('ipaymu.va');
        $apiKey = (string) config('ipaymu.api_key');
        if ($va === '') {
            return false;
        }

        $normalized = $payload;
        if (! array_key_exists('additional_info', $normalized)) {
            $normalized['additional_info'] = [];
        }

        ksort($normalized, SORT_STRING);

        $jsonCandidates = [
            json_encode($normalized),
            json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        foreach ($jsonCandidates as $json) {
            if ($json === false || $json === '') {
                continue;
            }

            $expected = hash_hmac('sha256', $json, $va);
            if (hash_equals($expected, $received)) {
                return true;
            }
        }

        if ($apiKey !== '') {
            $body = trim((string) ($rawBody ?? json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
            $hashedBody = strtolower(hash('sha256', $body));
            $legacyCandidates = [
                "POST:{$va}:{$hashedBody}:{$apiKey}",
                "POST:{$va}:{$hashedBody}:{$apiKey}:" . ($externalId ?? ''),
                "POST:{$va}:{$hashedBody}:" . ($externalId ?? '') . ":{$apiKey}",
                "POST:{$va}:{$hashedBody}:{$apiKey}:" . ($timestamp ?? ''),
                "POST:{$va}:{$hashedBody}:" . ($timestamp ?? '') . ":{$apiKey}",
                "POST:{$va}:{$hashedBody}:{$apiKey}:" . ($externalId ?? '') . ":" . ($timestamp ?? ''),
                "POST:{$va}:{$hashedBody}:" . ($externalId ?? '') . ":" . ($timestamp ?? '') . ":{$apiKey}",
                "POST:{$va}:" . ($externalId ?? '') . ":{$hashedBody}:{$apiKey}",
                "POST:{$va}:" . ($timestamp ?? '') . ":{$hashedBody}:{$apiKey}",
            ];

            foreach ($legacyCandidates as $candidate) {
                $expected = hash_hmac('sha256', $candidate, $apiKey);
                if (hash_equals($expected, $received)) {
                    return true;
                }
            }
        }

        Log::debug('iPaymu callback signature mismatch', [
            'received' => $received,
            'va' => $va,
            'normalized_payload' => $normalized,
            'json_candidates' => array_values(array_filter($jsonCandidates, fn ($value) => $value !== false && $value !== '')),
        ]);

        return false;
    }
}
