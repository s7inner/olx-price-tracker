<?php

namespace Tests\Unit\Services\Olx;

use App\Services\Olx\OlxListingMetadataExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OlxListingMetadataExtractorTest extends TestCase
{
    private OlxListingMetadataExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new OlxListingMetadataExtractor();
    }

    #[DataProvider('extractableHtmlProvider')]
    public function test_it_extracts_ad_id_from_supported_html_sources(string $listingHtml, string $expectedAdId): void
    {
        $adId = $this->extractor->extractAdIdFromListingHtml($listingHtml);
        $this->assertSame($expectedAdId, $adId);
    }

    public static function extractableHtmlProvider(): array
    {
        return [
            'ad-id query parameter link' => [
                self::adIdLinkHtml(),
                '12345678',
            ],
            'json-ld product schema sku' => [
                self::jsonLdProductHtml(),
                '87654321',
            ],
            'visible id label fallback' => [
                self::visibleIdHtml(),
                '99887766',
            ],
        ];
    }

    private static function adIdLinkHtml(): string
    {
        return '<a href="/payment?ad-id=12345678">Buy</a>';
    }

    private static function jsonLdProductHtml(): string
    {
        return <<<'HTML'
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "sku": "87654321"
}
</script>
HTML;
    }

    private static function visibleIdHtml(): string
    {
        return '<div>ID: <!-- -->99887766</div>';
    }

    public function test_it_throws_exception_when_ad_id_cannot_be_extracted(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not extract ad ID from listing page.');

        $this->extractor->extractAdIdFromListingHtml('<html><body>no ad id here</body></html>');
    }
}
