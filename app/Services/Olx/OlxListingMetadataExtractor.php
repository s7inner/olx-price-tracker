<?php

namespace App\Services\Olx;

use RuntimeException;

class OlxListingMetadataExtractor
{
    public function extractAdIdFromListingHtml(string $listingHtml): string
    {
        $adId = $this->extractAdIdFromLinks($listingHtml)
            ?? $this->extractAdIdFromJsonLdScripts($listingHtml)
            ?? $this->extractAdIdFromVisibleIdLabel($listingHtml);

        if ($adId === null) {
            throw new RuntimeException('Could not extract ad ID from listing page.');
        }

        return $adId;
    }

    private function extractAdIdFromLinks(string $listingHtml): ?string
    {
        if (preg_match('/[?&]ad-id=([0-9]{6,12})\b/i', $listingHtml, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function extractAdIdFromJsonLdScripts(string $listingHtml): ?string
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

        return null;
    }

    private function extractAdIdFromVisibleIdLabel(string $listingHtml): ?string
    {
        if (preg_match('/ID:\s*(?:<!--\s*-->)?\s*([0-9]{6,12})/iu', $listingHtml, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
