<?php

namespace Tests\Feature\Console;

use App\Enums\ListingNotificationType;
use App\Enums\ListingTrackingStatus;
use App\Jobs\SendListingNotificationEmailJob;
use App\Models\PriceSubscription;
use App\Models\TrackedAd;
use App\Services\Olx\OlxListingPageClient;
use App\Services\Olx\OlxPaymentPriceClient;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TrackOlxAdPriceChangesCommandTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_PRICE = 10_000;
    private const UPDATED_PRICE = 12_500;
    private const REACTIVATED_UPDATED_PRICE = 13_000;
    private const CURRENCY = 'UAH';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[DataProvider('statusTransitionProvider')]
    public function test_it_notifies_on_leaving_active_status_and_skips_price_api(
        int $statusCode,
        ListingTrackingStatus $expectedStatus,
        ListingNotificationType $expectedNotificationType
    ): void {
        $trackedAd = $this->createActiveTrackedAdWithSubscription();

        $this->mockListingStatus($trackedAd, $statusCode);
        $this->mockPriceApiNeverCalled();

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame($expectedStatus, $trackedAd->status);
        $this->assertSame(self::BASE_PRICE, $trackedAd->current_price_minor);

        $this->assertSingleNotificationPushed($expectedNotificationType);
    }

    public function test_it_notifies_price_changed_for_active_listing_and_updates_price(): void
    {
        $trackedAd = $this->createActiveTrackedAdWithSubscription();

        $this->mockListingStatus($trackedAd, Response::HTTP_OK);
        $this->mockPriceSnapshot($trackedAd, [
            'current_price_minor' => self::UPDATED_PRICE,
            'currency_code' => self::CURRENCY,
        ]);

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame(self::UPDATED_PRICE, $trackedAd->current_price_minor);
        $this->assertSame(self::CURRENCY, $trackedAd->currency_code);

        $this->assertSingleNotificationPushed(
            expectedNotificationType: ListingNotificationType::PRICE_CHANGED,
            expectedPreviousPriceMinor: self::BASE_PRICE,
            expectedCurrentPriceMinor: self::UPDATED_PRICE,
        );
    }

    public function test_it_does_not_notify_when_active_listing_price_is_unchanged(): void
    {
        $trackedAd = $this->createActiveTrackedAdWithSubscription();

        $this->mockListingStatus($trackedAd, Response::HTTP_OK);
        $this->mockPriceSnapshot($trackedAd, [
            'current_price_minor' => self::BASE_PRICE,
            'currency_code' => self::CURRENCY,
        ]);

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame(ListingTrackingStatus::ACTIVE, $trackedAd->status);
        $this->assertSame(self::BASE_PRICE, $trackedAd->current_price_minor);

        Queue::assertNothingPushed();
    }

    #[DataProvider('reactivationProvider')]
    public function test_it_handles_reactivation_with_single_expected_notification(
        ListingTrackingStatus $previousStatus,
        int $currentPriceMinor,
        ListingNotificationType $expectedNotificationType
    ): void {
        $trackedAd = $this->createTrackedAdWithSubscription([
            'status' => $previousStatus,
            'current_price_minor' => self::BASE_PRICE,
            'currency_code' => self::CURRENCY,
        ]);

        $this->mockListingStatus($trackedAd, Response::HTTP_OK);
        $this->mockPriceSnapshot($trackedAd, [
            'current_price_minor' => $currentPriceMinor,
            'currency_code' => self::CURRENCY,
        ]);

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame(ListingTrackingStatus::ACTIVE, $trackedAd->status);
        $this->assertSame($currentPriceMinor, $trackedAd->current_price_minor);

        $shouldContainPriceDelta = $expectedNotificationType === ListingNotificationType::LISTING_REACTIVATED_WITH_PRICE_CHANGE;
        $this->assertSingleNotificationPushed(
            expectedNotificationType: $expectedNotificationType,
            expectedPreviousPriceMinor: $shouldContainPriceDelta ? self::BASE_PRICE : null,
            expectedCurrentPriceMinor: $shouldContainPriceDelta ? $currentPriceMinor : null,
        );
    }

    public function test_it_ignores_tracked_ads_without_subscriptions(): void
    {
        TrackedAd::factory()->create();

        $this->mock(OlxListingPageClient::class, function ($mock): void {
            $mock->shouldReceive('fetch')->never();
        });
        $this->mockPriceApiNeverCalled();

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_it_updates_status_between_non_active_states_without_notification(): void
    {
        $trackedAd = $this->createTrackedAdWithSubscription([
            'status' => ListingTrackingStatus::NON_PUBLIC,
            'current_price_minor' => self::BASE_PRICE,
            'currency_code' => self::CURRENCY,
        ]);

        $this->mockListingStatus($trackedAd, Response::HTTP_GONE);
        $this->mockPriceApiNeverCalled();

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame(ListingTrackingStatus::INACTIVE, $trackedAd->status);
        $this->assertSame(self::BASE_PRICE, $trackedAd->current_price_minor);

        Queue::assertNothingPushed();
    }

    public function test_it_skips_processing_when_non_active_status_is_unchanged(): void
    {
        $trackedAd = $this->createTrackedAdWithSubscription([
            'status' => ListingTrackingStatus::NON_PUBLIC,
            'current_price_minor' => self::BASE_PRICE,
            'currency_code' => self::CURRENCY,
        ]);

        $this->mockListingStatus($trackedAd, Response::HTTP_NOT_FOUND);
        $this->mockPriceApiNeverCalled();

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame(ListingTrackingStatus::NON_PUBLIC, $trackedAd->status);
        $this->assertSame(self::BASE_PRICE, $trackedAd->current_price_minor);

        Queue::assertNothingPushed();
    }

    public function test_it_marks_listing_unavailable_when_status_check_fails(): void
    {
        $trackedAd = $this->createTrackedAdWithSubscription([
            'status' => ListingTrackingStatus::ACTIVE,
        ]);

        $this->mock(OlxListingPageClient::class, function ($mock) use ($trackedAd): void {
            $mock->shouldReceive('fetch')
                ->once()
                ->with($trackedAd->listing_url)
                ->andThrow(new \RuntimeException('status endpoint timeout'));
        });
        $this->mockPriceApiNeverCalled();

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame(ListingTrackingStatus::UNAVAILABLE, $trackedAd->status);

        $this->assertSingleNotificationPushed(ListingNotificationType::LISTING_UNAVAILABLE);
    }

    public function test_it_skips_notifications_and_persistence_when_price_api_fails(): void
    {
        $trackedAd = $this->createTrackedAdWithSubscription([
            'status' => ListingTrackingStatus::ACTIVE,
            'current_price_minor' => self::BASE_PRICE,
            'currency_code' => self::CURRENCY,
        ]);

        $this->mockListingStatus($trackedAd, Response::HTTP_OK);
        $this->mock(OlxPaymentPriceClient::class, function ($mock) use ($trackedAd): void {
            $mock->shouldReceive('fetchCurrentPriceByAdId')
                ->once()
                ->with($trackedAd->olx_ad_id)
                ->andThrow(new \RuntimeException('price endpoint timeout'));
        });

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        $trackedAd->refresh();
        $this->assertSame(ListingTrackingStatus::ACTIVE, $trackedAd->status);
        $this->assertSame(self::BASE_PRICE, $trackedAd->current_price_minor);

        Queue::assertNothingPushed();
    }

    public function test_it_dispatches_notification_for_each_subscription(): void
    {
        $trackedAd = $this->createActiveTrackedAdWithSubscription();
        PriceSubscription::factory()->create(['tracked_ad_id' => $trackedAd->id]);

        $this->mockListingStatus($trackedAd, Response::HTTP_OK);
        $this->mockPriceSnapshot($trackedAd, [
            'current_price_minor' => self::UPDATED_PRICE,
            'currency_code' => self::CURRENCY,
        ]);

        $this->artisan('ads:check-olx-prices')->assertSuccessful();

        Queue::assertPushed(SendListingNotificationEmailJob::class, 2);
        Queue::assertPushed(SendListingNotificationEmailJob::class, function (SendListingNotificationEmailJob $job): bool {
            return $job->notificationType() === ListingNotificationType::PRICE_CHANGED
                && $job->previousPriceMinor() === self::BASE_PRICE
                && $job->currentPriceMinor() === self::UPDATED_PRICE;
        });
    }

    public static function statusTransitionProvider(): array
    {
        return [
            'active to non-public' => [Response::HTTP_NOT_FOUND, ListingTrackingStatus::NON_PUBLIC, ListingNotificationType::LISTING_NON_PUBLIC],
            'active to inactive' => [Response::HTTP_GONE, ListingTrackingStatus::INACTIVE, ListingNotificationType::LISTING_INACTIVE],
            'active to unavailable' => [Response::HTTP_INTERNAL_SERVER_ERROR, ListingTrackingStatus::UNAVAILABLE, ListingNotificationType::LISTING_UNAVAILABLE],
        ];
    }

    public static function reactivationProvider(): array
    {
        return [
            'non-public -> active, same price' => [
                ListingTrackingStatus::NON_PUBLIC,
                self::BASE_PRICE,
                ListingNotificationType::LISTING_REACTIVATED,
            ],
            'inactive -> active, same price' => [
                ListingTrackingStatus::INACTIVE,
                self::BASE_PRICE,
                ListingNotificationType::LISTING_REACTIVATED,
            ],
            'unavailable -> active, same price' => [
                ListingTrackingStatus::UNAVAILABLE,
                self::BASE_PRICE,
                ListingNotificationType::LISTING_REACTIVATED,
            ],
            'non-public -> active, changed price' => [
                ListingTrackingStatus::NON_PUBLIC,
                self::REACTIVATED_UPDATED_PRICE,
                ListingNotificationType::LISTING_REACTIVATED_WITH_PRICE_CHANGE,
            ],
            'inactive -> active, changed price' => [
                ListingTrackingStatus::INACTIVE,
                self::REACTIVATED_UPDATED_PRICE,
                ListingNotificationType::LISTING_REACTIVATED_WITH_PRICE_CHANGE,
            ],
            'unavailable -> active, changed price' => [
                ListingTrackingStatus::UNAVAILABLE,
                self::REACTIVATED_UPDATED_PRICE,
                ListingNotificationType::LISTING_REACTIVATED_WITH_PRICE_CHANGE,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $trackedAdAttributes
     */
    private function createTrackedAdWithSubscription(array $trackedAdAttributes = []): TrackedAd
    {
        $trackedAd = TrackedAd::factory()->create($trackedAdAttributes);
        PriceSubscription::factory()->create(['tracked_ad_id' => $trackedAd->id]);

        return $trackedAd;
    }

    private function createActiveTrackedAdWithSubscription(): TrackedAd
    {
        return $this->createTrackedAdWithSubscription([
            'status' => ListingTrackingStatus::ACTIVE,
            'current_price_minor' => self::BASE_PRICE,
            'currency_code' => self::CURRENCY,
        ]);
    }

    private function mockListingStatus(TrackedAd $trackedAd, int $statusCode): void
    {
        $this->mock(OlxListingPageClient::class, function ($mock) use ($trackedAd, $statusCode): void {
            $mock->shouldReceive('fetch')
                ->once()
                ->with($trackedAd->listing_url)
                ->andReturn(new HttpClientResponse(new Psr7Response($statusCode)));
        });
    }

    /**
     * @param array{current_price_minor: int, currency_code: string} $snapshot
     */
    private function mockPriceSnapshot(TrackedAd $trackedAd, array $snapshot): void
    {
        $this->mock(OlxPaymentPriceClient::class, function ($mock) use ($trackedAd, $snapshot): void {
            $mock->shouldReceive('fetchCurrentPriceByAdId')
                ->once()
                ->with($trackedAd->olx_ad_id)
                ->andReturn($snapshot);
        });
    }

    private function mockPriceApiNeverCalled(): void
    {
        $this->mock(OlxPaymentPriceClient::class, function ($mock): void {
            $mock->shouldReceive('fetchCurrentPriceByAdId')->never();
        });
    }

    private function assertSingleNotificationPushed(
        ListingNotificationType $expectedNotificationType,
        ?int $expectedPreviousPriceMinor = null,
        ?int $expectedCurrentPriceMinor = null
    ): void {
        Queue::assertPushed(SendListingNotificationEmailJob::class, 1);
        Queue::assertPushed(SendListingNotificationEmailJob::class, function (SendListingNotificationEmailJob $job) use (
            $expectedNotificationType,
            $expectedPreviousPriceMinor,
            $expectedCurrentPriceMinor
        ): bool {
            return $job->notificationType() === $expectedNotificationType
                && $job->previousPriceMinor() === $expectedPreviousPriceMinor
                && $job->currentPriceMinor() === $expectedCurrentPriceMinor;
        });
    }
}
