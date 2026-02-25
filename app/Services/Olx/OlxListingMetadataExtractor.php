<?php

namespace App\Services\Olx;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OlxListingMetadataExtractor
{
    public function extractAdIdFromListingPage(string $listingUrl): string
    {
        $listingPageResponse = Http::accept('text/html')
            ->get($listingUrl);

        if ($listingPageResponse->failed()) {
            throw new RuntimeException('Failed to fetch listing page.');
        }

        $listingHtml = $listingPageResponse->body();
        return $this->extractAdIdFromJsonLdScripts($listingHtml);
    }

    private function extractAdIdFromJsonLdScripts(string $listingHtml): string
    {
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $listingHtml, $jsonLdMatches);
        $jsonLdScriptBodies = $jsonLdMatches[1] ?? [];

        foreach ($jsonLdScriptBodies as $jsonLdScriptBody) {
            $decodedJsonLd = json_decode(trim($jsonLdScriptBody), true);

            if (! is_array($decodedJsonLd)) {
                continue;
            }

            $schemaType = $decodedJsonLd['@type'] ?? null;
            $isProductSchema = is_string($schemaType) && strtolower($schemaType) === 'product';

            if (! $isProductSchema) {
                continue;
            }

            $rawOlxAdId = $decodedJsonLd['sku'] ?? null;

            if (is_scalar($rawOlxAdId) && trim((string) $rawOlxAdId) !== '') {
                return trim((string) $rawOlxAdId);
            }
        }

        throw new RuntimeException('Product schema with valid SKU was not found on listing page.');
    }
}
