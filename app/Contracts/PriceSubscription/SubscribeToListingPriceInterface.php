<?php

namespace App\Contracts\PriceSubscription;

use App\Services\PriceSubscription\SubscriptionDTO;

interface SubscribeToListingPriceInterface
{
    public function __invoke(string $listingUrl, int $userId): SubscriptionDTO;
}
