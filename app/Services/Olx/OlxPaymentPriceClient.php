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
        $paymentApiResponse = Http::timeout(15)
            ->acceptJson()
            ->get($this->buildPaymentApiUrl($olxAdId));

        if ($paymentApiResponse->failed()) {
            throw new RuntimeException('Failed to fetch price from OLX payment API.');
        }

        /** @var array<string, mixed> $paymentApiPayload */
        $paymentApiPayload = $paymentApiResponse->json();
        $rawCurrentPriceMinor = data_get($paymentApiPayload, 'product.price');
        $rawCurrencyCode = data_get($paymentApiPayload, 'product.currency');

        if (! is_numeric($rawCurrentPriceMinor)) {
            throw new RuntimeException('OLX payment API did not return a valid numeric price.');
        }

        if (! is_string($rawCurrencyCode) || trim($rawCurrencyCode) === '') {
            throw new RuntimeException('OLX payment API did not return a valid currency code.');
        }

        return [
            'current_price_minor' => (int) $rawCurrentPriceMinor,
            'currency_code' => strtoupper(trim($rawCurrencyCode)),
        ];
    }

    private function buildPaymentApiUrl(string $olxAdId): string
    {
        return "https://ua.production.delivery.olx.tools/payment/ad/{$olxAdId}/buyer/?lang=uk-UA";
    }
}
