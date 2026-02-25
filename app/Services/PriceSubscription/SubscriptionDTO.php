<?php

namespace App\Services\PriceSubscription;

use App\Models\PriceSubscription;
use App\Models\TrackedAd;

final readonly class SubscriptionDTO
{
    public function __construct(
        public int    $subscriptionId,
        public bool   $isNewSubscription,
        public string $listingUrl,
        public int    $currentPriceMinor,
        public string $currencyCode,
    ) {
    }

    public static function fromModels(TrackedAd $trackedAd, PriceSubscription $subscription): self
    {
        return new self(
            subscriptionId: $subscription->id,
            isNewSubscription: $subscription->wasRecentlyCreated,
            listingUrl: $trackedAd->listing_url,
            currentPriceMinor: (int) $trackedAd->current_price_minor,
            currencyCode: $trackedAd->currency_code,
        );
    }
}
