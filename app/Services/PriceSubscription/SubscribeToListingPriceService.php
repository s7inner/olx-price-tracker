<?php

namespace App\Services\PriceSubscription;

use App\Models\PriceSubscription;
use App\Models\TrackedAd;
use App\Services\Olx\OlxListingMetadataExtractor;
use App\Services\Olx\OlxPaymentPriceClient;
use Illuminate\Support\Facades\DB;

readonly class SubscribeToListingPriceService
{
    public function __construct(
        private OlxListingMetadataExtractor $olxListingMetadataExtractor,
        private OlxPaymentPriceClient       $olxPaymentPriceClient,
    ) {
    }

    public function handle(string $listingUrl, string $subscriberEmail): SubscriptionDTO
    {
        $existingTrackedAd = TrackedAd::query()
            ->where('listing_url', $listingUrl)
            ->first();

        if ($existingTrackedAd !== null) {
            $subscription = $this->createSubscription($existingTrackedAd, $subscriberEmail);

            return SubscriptionDTO::fromModels($existingTrackedAd, $subscription);
        }

        $olxAdId = $this->olxListingMetadataExtractor->extractAdIdFromListingPage($listingUrl);
        $currentPriceSnapshot = $this->olxPaymentPriceClient->fetchCurrentPriceByAdId($olxAdId);

        /** @var array{tracked_ad: TrackedAd, subscription: PriceSubscription} $persistedSubscriptionData */
        $persistedSubscriptionData = DB::transaction(function () use (
            $listingUrl,
            $subscriberEmail,
            $olxAdId,
            $currentPriceSnapshot
        ): array {
            $trackedAd = TrackedAd::updateOrCreate(
                ['olx_ad_id' => $olxAdId],
                [
                    'listing_url' => $listingUrl,
                    'current_price_minor' => $currentPriceSnapshot['current_price_minor'],
                    'currency_code' => $currentPriceSnapshot['currency_code'],
                    'last_checked_at' => now(),
                ]
            );

            $subscription = $this->createSubscription($trackedAd, $subscriberEmail);

            return [
                'tracked_ad' => $trackedAd,
                'subscription' => $subscription,
            ];
        });

        return SubscriptionDTO::fromModels(
            $persistedSubscriptionData['tracked_ad'],
            $persistedSubscriptionData['subscription']
        );
    }

    private function createSubscription(TrackedAd $trackedAd, string $subscriberEmail): PriceSubscription
    {
        return PriceSubscription::firstOrCreate([
            'tracked_ad_id' => $trackedAd->id,
            'subscriber_email' => $subscriberEmail,
        ]);
    }
}
