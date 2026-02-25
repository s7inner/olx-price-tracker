<?php

namespace App\Services\Olx;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OlxListingMetadataExtractor
{
    public function extractAdIdFromListingPage(string $listingUrl): string
    {
        $listingPageResponse = Http::timeout(15)
            ->accept('text/html')
            ->get($listingUrl);

        if ($listingPageResponse->failed()) {
            throw new RuntimeException('Failed to fetch listing page.');
        }

        $listingHtml = $listingPageResponse->body();
        $productSchemaData = $this->extractProductSchemaData($listingHtml);
        $rawOlxAdId = $productSchemaData['sku'] ?? null;

        if (! is_scalar($rawOlxAdId) || trim((string) $rawOlxAdId) === '') {
            throw new RuntimeException('Failed to extract ad ID from listing page schema.');
        }

        return trim((string) $rawOlxAdId);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractProductSchemaData(string $listingHtml): array
    {
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $listingHtml, $jsonLdMatches);
        $jsonLdScriptBodies = $jsonLdMatches[1] ?? [];

        foreach ($jsonLdScriptBodies as $jsonLdScriptBody) {
            $decodedJsonLd = json_decode(trim($jsonLdScriptBody), true);

            if (! is_array($decodedJsonLd)) {
                continue;
            }

            $productSchemaData = $this->findFirstProductSchemaNode($decodedJsonLd);

            if ($productSchemaData !== null) {
                return $productSchemaData;
            }
        }

        throw new RuntimeException('Product schema in JSON-LD was not found on listing page.');
    }

    /**
     * @param  array<mixed>  $jsonNode
     * @return array<string, mixed>|null
     */
    private function findFirstProductSchemaNode(array $jsonNode): ?array
    {
        if ($this->isProductSchemaNode($jsonNode)) {
            return $jsonNode;
        }

        foreach ($jsonNode as $childNode) {
            if (! is_array($childNode)) {
                continue;
            }

            $foundProductSchemaNode = $this->findFirstProductSchemaNode($childNode);

            if ($foundProductSchemaNode !== null) {
                return $foundProductSchemaNode;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schemaNode
     */
    private function isProductSchemaNode(array $schemaNode): bool
    {
        $schemaType = $schemaNode['@type'] ?? null;

        if (is_string($schemaType)) {
            return strtolower($schemaType) === 'product';
        }

        if (is_array($schemaType)) {
            $normalizedSchemaTypes = array_map(
                static fn (mixed $value): string => is_scalar($value) ? strtolower((string) $value) : '',
                $schemaType
            );

            return in_array('product', $normalizedSchemaTypes, true);
        }

        return false;
    }
}
