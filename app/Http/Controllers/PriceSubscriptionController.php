<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceSubscriptionRequest;
use App\Models\PriceSubscription;
use App\Models\TrackedAd;
use App\Services\Olx\OlxListingMetadataExtractor;
use App\Services\Olx\OlxPaymentPriceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class PriceSubscriptionController extends Controller
{
    public function __construct(
        private readonly OlxListingMetadataExtractor $olxListingMetadataExtractor,
        private readonly OlxPaymentPriceClient $olxPaymentPriceClient,
    ) {
    }

    public function subscribeToListingPrice(StorePriceSubscriptionRequest $request): JsonResponse
    {
        $listingUrl = trim((string) $request->input('listing_url'));
        $subscriberEmail = mb_strtolower(trim((string) $request->input('subscriber_email')));

        try {
            $olxAdId = $this->olxListingMetadataExtractor->extractAdIdFromListingPage($listingUrl);
            $currentPriceSnapshot = $this->olxPaymentPriceClient->fetchCurrentPriceByAdId($olxAdId);
        } catch (Throwable $throwable) {
            return response()->json([
                'message' => 'Could not subscribe to this listing. Please verify URL and try again.',
                'error' => $throwable->getMessage(),
            ], 422);
        }

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

            $subscription = PriceSubscription::firstOrCreate([
                'tracked_ad_id' => $trackedAd->id,
                'subscriber_email' => $subscriberEmail,
            ]);

            return [
                'tracked_ad' => $trackedAd,
                'subscription' => $subscription,
            ];
        });

        $trackedAd = $persistedSubscriptionData['tracked_ad'];
        $subscription = $persistedSubscriptionData['subscription'];
        $responseStatusCode = $subscription->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'message' => 'Subscription saved successfully.',
            'tracked_ad_id' => $trackedAd->id,
            'olx_ad_id' => $trackedAd->olx_ad_id,
            'listing_url' => $trackedAd->listing_url,
            'current_price_minor' => $trackedAd->current_price_minor,
            'currency_code' => $trackedAd->currency_code,
            'is_new_subscription' => $subscription->wasRecentlyCreated,
        ], $responseStatusCode);
    }
}
