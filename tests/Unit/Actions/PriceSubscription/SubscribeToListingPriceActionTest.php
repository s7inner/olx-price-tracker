<?php

namespace Tests\Unit\Actions\PriceSubscription;

use App\Actions\PriceSubscription\SubscribeToListingPriceAction;
use App\Enums\ListingTrackingStatus;
use App\Exceptions\ListingPreflightException;
use App\Models\PriceSubscription;
use App\Models\TrackedAd;
use App\Models\User;
use App\Services\Olx\OlxListingAvailabilityChecker;
use App\Services\Olx\OlxListingMetadataExtractor;
use App\Services\Olx\OlxListingPageClient;
use App\Services\Olx\OlxPaymentPriceClient;
use App\Services\PriceSubscription\SubscriptionDTO;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SubscribeToListingPriceActionTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_URL = 'https://www.olx.ua/d/uk/obyavlenie/example-IDTEST01.html';
    private const OLX_AD_ID = '12345678';
    private const BASE_PRICE_MINOR = 100_000;
    private const UPDATED_PRICE_MINOR = 125_000;
    private const CURRENCY_CODE = 'UAH';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_it_returns_existing_subscription_for_existing_tracked_ad(): void
    {
        $trackedAd = $this->createActiveTrackedAd();
        $existingSubscription = PriceSubscription::factory()->create([
            'tracked_ad_id' => $trackedAd->id,
            'user_id' => $this->user->id,
        ]);

        $action = $this->makeActionWithMocks(
            listingPageClient: Mockery::mock(OlxListingPageClient::class),
            availabilityChecker: Mockery::mock(OlxListingAvailabilityChecker::class),
            metadataExtractor: Mockery::mock(OlxListingMetadataExtractor::class),
            paymentPriceClient: Mockery::mock(OlxPaymentPriceClient::class),
        );

        $dto = $action(self::LISTING_URL, $this->user->id);

        $this->assertSame($existingSubscription->id, $dto->subscriptionId);
        $this->assertFalse($dto->isNewSubscription);
        $this->assertSubscriptionDtoDetails($dto, self::BASE_PRICE_MINOR);

        $this->assertDatabaseCount('tracked_ads', 1);
        $this->assertDatabaseCount('price_subscriptions', 1);
    }

    public function test_it_creates_new_subscription_for_existing_tracked_ad(): void
    {
        $trackedAd = $this->createActiveTrackedAd();

        $action = $this->makeActionWithMocks(
            listingPageClient: Mockery::mock(OlxListingPageClient::class),
            availabilityChecker: Mockery::mock(OlxListingAvailabilityChecker::class),
            metadataExtractor: Mockery::mock(OlxListingMetadataExtractor::class),
            paymentPriceClient: Mockery::mock(OlxPaymentPriceClient::class),
        );

        $dto = $action(self::LISTING_URL, $this->user->id);

        $this->assertTrue($dto->isNewSubscription);
        $this->assertSubscriptionDtoDetails($dto, self::BASE_PRICE_MINOR);

        $this->assertDatabaseCount('tracked_ads', 1);
        $this->assertDatabaseCount('price_subscriptions', 1);
        $this->assertDatabaseHas('price_subscriptions', [
            'tracked_ad_id' => $trackedAd->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_creates_tracked_ad_and_subscription_for_new_listing(): void
    {
        $listingResponse = new HttpClientResponse(new Psr7Response(Response::HTTP_OK, body: '<a href="?ad-id=12345678">ad</a>'));

        $listingPageClient = Mockery::mock(OlxListingPageClient::class);
        $listingPageClient->shouldReceive('fetch')
            ->once()
            ->with(self::LISTING_URL)
            ->andReturn($listingResponse);

        $availabilityChecker = Mockery::mock(OlxListingAvailabilityChecker::class);
        $availabilityChecker->shouldReceive('assertSubscribableResponse')
            ->once()
            ->with($listingResponse);

        $metadataExtractor = Mockery::mock(OlxListingMetadataExtractor::class);
        $metadataExtractor->shouldReceive('extractAdIdFromListingHtml')
            ->once()
            ->with($listingResponse->body())
            ->andReturn(self::OLX_AD_ID);

        $paymentPriceClient = Mockery::mock(OlxPaymentPriceClient::class);
        $paymentPriceClient->shouldReceive('fetchCurrentPriceByAdId')
            ->once()
            ->with(self::OLX_AD_ID)
            ->andReturn([
                'current_price_minor' => self::UPDATED_PRICE_MINOR,
                'currency_code' => self::CURRENCY_CODE,
            ]);

        $action = $this->makeActionWithMocks(
            listingPageClient: $listingPageClient,
            availabilityChecker: $availabilityChecker,
            metadataExtractor: $metadataExtractor,
            paymentPriceClient: $paymentPriceClient,
        );

        $dto = $action(self::LISTING_URL, $this->user->id);

        $this->assertTrue($dto->isNewSubscription);
        $this->assertSubscriptionDtoDetails($dto, self::UPDATED_PRICE_MINOR);

        $this->assertDatabaseHas('tracked_ads', [
            'olx_ad_id' => self::OLX_AD_ID,
            'listing_url' => self::LISTING_URL,
            'current_price_minor' => self::UPDATED_PRICE_MINOR,
            'currency_code' => self::CURRENCY_CODE,
            'status' => ListingTrackingStatus::ACTIVE->value,
        ]);
        $this->assertDatabaseHas('price_subscriptions', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_propagates_preflight_exception_when_listing_is_not_subscribable(): void
    {
        $listingResponse = new HttpClientResponse(new Psr7Response(Response::HTTP_NOT_FOUND));

        $listingPageClient = Mockery::mock(OlxListingPageClient::class);
        $listingPageClient->shouldReceive('fetch')
            ->once()
            ->with(self::LISTING_URL)
            ->andReturn($listingResponse);

        $availabilityChecker = Mockery::mock(OlxListingAvailabilityChecker::class);
        $availabilityChecker->shouldReceive('assertSubscribableResponse')
            ->once()
            ->with($listingResponse)
            ->andThrow(ListingPreflightException::notPublic());

        $metadataExtractor = Mockery::mock(OlxListingMetadataExtractor::class);
        $paymentPriceClient = Mockery::mock(OlxPaymentPriceClient::class);

        $action = $this->makeActionWithMocks(
            listingPageClient: $listingPageClient,
            availabilityChecker: $availabilityChecker,
            metadataExtractor: $metadataExtractor,
            paymentPriceClient: $paymentPriceClient,
        );

        $this->expectException(ListingPreflightException::class);
        $action(self::LISTING_URL, $this->user->id);

        $this->assertDatabaseCount('tracked_ads', 0);
        $this->assertDatabaseCount('price_subscriptions', 0);
    }

    private function makeActionWithMocks(
        OlxListingPageClient $listingPageClient,
        OlxListingAvailabilityChecker $availabilityChecker,
        OlxListingMetadataExtractor $metadataExtractor,
        OlxPaymentPriceClient $paymentPriceClient
    ): SubscribeToListingPriceAction {
        return new SubscribeToListingPriceAction(
            olxListingPageClient: $listingPageClient,
            olxListingAvailabilityChecker: $availabilityChecker,
            olxListingMetadataExtractor: $metadataExtractor,
            olxPaymentPriceClient: $paymentPriceClient,
        );
    }

    private function createActiveTrackedAd(array $attributes = []): TrackedAd
    {
        return TrackedAd::factory()->create(array_replace([
            'olx_ad_id' => self::OLX_AD_ID,
            'listing_url' => self::LISTING_URL,
            'current_price_minor' => self::BASE_PRICE_MINOR,
            'currency_code' => self::CURRENCY_CODE,
            'status' => ListingTrackingStatus::ACTIVE,
        ], $attributes));
    }

    private function assertSubscriptionDtoDetails(SubscriptionDTO $dto, int $expectedPriceMinor): void
    {
        $this->assertSame(self::LISTING_URL, $dto->listingUrl);
        $this->assertSame($expectedPriceMinor, $dto->currentPriceMinor);
        $this->assertSame(self::CURRENCY_CODE, $dto->currencyCode);
    }
}
