<?php

namespace App\Services\Olx;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OlxPaymentPriceClient
{
    /**
     * @return array{current_price_minor: int, currency_code: string}
     */
    public function fetchCurrentPriceByAdId(string $olxAdId): array
    {
        /** @var array<string, mixed> $paymentApiPayload */
        $paymentApiPayload = Http::acceptJson()
            ->get($this->buildPaymentApiUrl($olxAdId))
            ->throw()
            ->json();

        $rawCurrentPriceMinor = data_get($paymentApiPayload, 'product.price');
        $currencyCode = strtoupper(trim((string) data_get($paymentApiPayload, 'product.currency', '')));

        if (! is_numeric($rawCurrentPriceMinor)) {
            throw new RuntimeException('OLX payment API did not return a valid numeric price.');
        }

        if ($currencyCode === '') {
            throw new RuntimeException('OLX payment API did not return a valid currency code.');
        }

        return [
            'current_price_minor' => (int) $rawCurrentPriceMinor,
            'currency_code' => $currencyCode,
        ];
    }

    private function buildPaymentApiUrl(string $olxAdId): string
    {
        $paymentBaseUrl = rtrim((string) config('olx.payment_base_url'), '/');

        return "{$paymentBaseUrl}/payment/ad/{$olxAdId}/buyer/";
    }
}
