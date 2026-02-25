<?php

namespace App\Http\Resources;

use App\Services\PriceSubscription\SubscriptionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubscriptionDTO */
class PriceSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'is_new_subscription' => $this->isNewSubscription,
            'listing_url' => $this->listingUrl,
            'current_price_minor' => $this->currentPriceMinor,
            'currency_code' => $this->currencyCode,
        ];
    }
}
