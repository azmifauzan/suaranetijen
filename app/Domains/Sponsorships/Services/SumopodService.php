<?php

namespace App\Domains\Sponsorships\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SumopodService
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('sponsorship.sumopod.base_url', 'https://api-pay.sumopod.com'), '/');
        $this->apiKey = (string) config('sponsorship.sumopod.api_key', '');
    }

    /**
     * Create a payment link for a sponsorship order.
     *
     * @param  array{order_id: string, amount: int, redirect_url: string}  $params
     * @return array{payment_id: string, payment_link_url: string, status: string}
     *
     * @throws RuntimeException
     */
    public function createPayment(array $params): array
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->timeout(30)
            ->post("{$this->baseUrl}/api/v1/payments", [
                'order_id' => $params['order_id'],
                'amount' => $params['amount'],
                'currency' => 'IDR',
                'success_return_url' => $params['redirect_url'],
                'cancel_return_url' => $params['redirect_url'],
                'payment_method_type_code' => 'QRIS',
            ]);

        if (! $response->successful()) {
            Log::error('Sumopod payment creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_id' => $params['order_id'],
            ]);

            $errorMsg = $response->json('message') ?? 'Unknown error';

            throw new RuntimeException("Gagal membuat link pembayaran: {$errorMsg}");
        }

        $data = $response->json();
        if (! is_array($data)
            || ! is_string($data['payment_id'] ?? null) || $data['payment_id'] === ''
            || ! is_string($data['payment_link_url'] ?? null) || $data['payment_link_url'] === '') {
            Log::error('Sumopod payment creation returned an invalid response', [
                'order_id' => $params['order_id'],
            ]);

            throw new RuntimeException('Respons pembayaran tidak valid.');
        }

        return [
            'payment_id' => $data['payment_id'],
            'payment_link_url' => $data['payment_link_url'],
            'status' => $data['status'] ?? 'pending',
        ];
    }
}
