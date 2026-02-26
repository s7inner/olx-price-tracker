<?php

namespace App\Console\Commands;

use App\Enums\ListingNotificationType;
use App\Jobs\SendListingNotificationEmailJob;
use App\Models\TrackedAd;
use App\Services\Olx\OlxPaymentPriceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackOlxAdPriceChangesCommand extends Command
{
    protected $signature = 'ads:check-olx-prices';

    protected $description = 'Check tracked OLX listings and notify subscribers on price changes.';

    public function __construct(
        private readonly OlxPaymentPriceClient $olxPaymentPriceClient,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        TrackedAd::query()
            ->whereHas('subscriptions')
            ->with('subscriptions')
            ->chunkById(100, function ($trackedAds): void {
                foreach ($trackedAds as $trackedAd) {
                    $this->checkTrackedAdPrice($trackedAd);
                }
            });

        return self::SUCCESS;
    }

    private function checkTrackedAdPrice(TrackedAd $trackedAd): void
    {
        try {
            $listingPageStatusCode = Http::accept('text/html')
                ->get($trackedAd->listing_url)
                ->status();
        } catch (Throwable $throwable) {
            Log::warning('Failed to check tracked OLX listing page status.', [
                'tracked_ad_id' => $trackedAd->id,
                'olx_ad_id' => $trackedAd->olx_ad_id,
                'listing_url' => $trackedAd->listing_url,
                'error' => $throwable->getMessage(),
            ]);

            return;
        }

        if ($listingPageStatusCode === Response::HTTP_GONE) {
            $this->notifyListingInactive($trackedAd);

            return;
        }

        if ($listingPageStatusCode !== Response::HTTP_OK) {
            Log::warning('Unexpected tracked OLX listing page status.', [
                'tracked_ad_id' => $trackedAd->id,
                'olx_ad_id' => $trackedAd->olx_ad_id,
                'listing_url' => $trackedAd->listing_url,
                'status_code' => $listingPageStatusCode,
            ]);

            return;
        }

        if ($trackedAd->listing_inactive_notified_at !== null) {
            $trackedAd->forceFill([
                'listing_inactive_notified_at' => null,
            ])->save();
        }

        try {
            $currentPriceSnapshot = $this->olxPaymentPriceClient->fetchCurrentPriceByAdId($trackedAd->olx_ad_id);
        } catch (Throwable $throwable) {
            Log::warning('Failed to check tracked OLX ad price.', [
                'tracked_ad_id' => $trackedAd->id,
                'olx_ad_id' => $trackedAd->olx_ad_id,
                'listing_url' => $trackedAd->listing_url,
                'error' => $throwable->getMessage(),
            ]);

            return;
        }

        $previousPriceMinor = (int) $trackedAd->current_price_minor;
        $currentPriceMinor = $currentPriceSnapshot['current_price_minor'];
        $priceHasChanged = $currentPriceMinor !== $previousPriceMinor;

        $trackedAd->forceFill([
            'current_price_minor' => $currentPriceMinor,
            'currency_code' => $currentPriceSnapshot['currency_code'],
            'last_checked_at' => now(),
        ])->save();

        if (! $priceHasChanged) {
            return;
        }

        foreach ($trackedAd->subscriptions as $subscription) {
            SendListingNotificationEmailJob::dispatch(
                subscriberEmail: $subscription->subscriber_email,
                listingUrl: $trackedAd->listing_url,
                notificationType: ListingNotificationType::PRICE_CHANGED,
                previousPriceMinor: $previousPriceMinor,
                currentPriceMinor: $currentPriceMinor,
                currencyCode: $trackedAd->currency_code,
            );
        }
    }

    private function notifyListingInactive(TrackedAd $trackedAd): void
    {
        if ($trackedAd->listing_inactive_notified_at !== null) {
            return;
        }

        foreach ($trackedAd->subscriptions as $subscription) {
            SendListingNotificationEmailJob::dispatch(
                subscriberEmail: $subscription->subscriber_email,
                listingUrl: $trackedAd->listing_url,
                notificationType: ListingNotificationType::LISTING_INACTIVE,
            );
        }

        $trackedAd->forceFill([
            'listing_inactive_notified_at' => now(),
            'last_checked_at' => now(),
        ])->save();
    }
}
