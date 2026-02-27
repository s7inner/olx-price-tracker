<?php

namespace App\Actions\PriceSubscription;

use App\Contracts\PriceSubscription\SubscribeToListingPriceInterface;
use App\Enums\ListingTrackingStatus;
use App\Models\PriceSubscription;
use App\Models\TrackedAd;
use App\Services\Olx\OlxListingAvailabilityChecker;
use App\Services\Olx\OlxListingMetadataExtractor;
use App\Services\Olx\OlxListingPageClient;
use App\Services\Olx\OlxPaymentPriceClient;
use App\Services\PriceSubscription\SubscriptionDTO;
use Illuminate\Support\Facades\DB;

readonly class SubscribeToListingPriceAction implements SubscribeToListingPriceInterface
{
    public function __construct(
        private OlxListingPageClient $olxListingPageClient,
        private OlxListingAvailabilityChecker $olxListingAvailabilityChecker,
        private OlxListingMetadataExtractor $olxListingMetadataExtractor,
        private OlxPaymentPriceClient $olxPaymentPriceClient,
    ) {
    }

    public function __invoke(string $listingUrl, int $userId): SubscriptionDTO
    {
        $existingTrackedAd = TrackedAd::query()
            ->where('listing_url', $listingUrl)
            ->first();

        if ($existingTrackedAd !== null) {
            $subscription = $this->ensureSubscription($existingTrackedAd, $userId);

            return SubscriptionDTO::fromModels($existingTrackedAd, $subscription);
        }

        return $this->createTrackedAdAndSubscribe($listingUrl, $userId);
    }

    private function createTrackedAdAndSubscribe(string $listingUrl, int $userId): SubscriptionDTO
    {
        $listingPageResponse = $this->olxListingPageClient->fetch($listingUrl);
        $this->olxListingAvailabilityChecker->assertSubscribableResponse($listingPageResponse);

        $olxAdId = $this->olxListingMetadataExtractor->extractAdIdFromListingHtml($listingPageResponse->body());
        $currentPriceSnapshot = $this->olxPaymentPriceClient->fetchCurrentPriceByAdId($olxAdId);

        /* Tip:
         * https://www.olx.ua/d/uk/obyavlenie/-IDZXcb0.html is also a valid URL
         * OLX primarily uses the -ID<token>.html pattern
         */

        return DB::transaction(function () use (
            $listingUrl,
            $userId,
            $olxAdId,
            $currentPriceSnapshot
        ): SubscriptionDTO {
            $trackedAd = TrackedAd::updateOrCreate(
                ['olx_ad_id' => $olxAdId],
                [
                    'listing_url' => $listingUrl,
                    'current_price_minor' => $currentPriceSnapshot['current_price_minor'],
                    'currency_code' => $currentPriceSnapshot['currency_code'],
                    'status' => ListingTrackingStatus::ACTIVE,
                ]
            );

            $subscription = $this->ensureSubscription($trackedAd, $userId);

            return SubscriptionDTO::fromModels($trackedAd, $subscription);
        });
    }

    private function ensureSubscription(TrackedAd $trackedAd, int $userId): PriceSubscription
    {
        return PriceSubscription::firstOrCreate([
            'tracked_ad_id' => $trackedAd->id,
            'user_id' => $userId,
        ]);
    }
}
