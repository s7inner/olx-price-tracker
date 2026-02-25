<?php

namespace App\Console\Commands;

use App\Jobs\SendPriceChangedEmailJob;
use App\Models\TrackedAd;
use App\Services\Olx\OlxListingMetadataExtractor;
use App\Services\Olx\OlxPaymentPriceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackOlxAdPriceChangesCommand extends Command
{
    protected $signature = 'ads:check-olx-prices';

    protected $description = 'Check tracked OLX listings and notify subscribers on price changes.';

    public function __construct(
        private readonly OlxPaymentPriceClient $olxPaymentPriceClient,
        private readonly OlxListingMetadataExtractor $olxListingMetadataExtractor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        TrackedAd::query()
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
            $currentPriceSnapshot = $this->fetchCurrentPriceWithFallback($trackedAd);
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
            SendPriceChangedEmailJob::dispatch(
                subscriberEmail: $subscription->subscriber_email,
                listingUrl: $trackedAd->listing_url,
                previousPriceMinor: $previousPriceMinor,
                currentPriceMinor: $currentPriceMinor,
                currencyCode: $trackedAd->currency_code,
            );
        }
    }

    /**
     * @return array{current_price_minor: int, currency_code: string}
     */
    private function fetchCurrentPriceWithFallback(TrackedAd $trackedAd): array
    {
        try {
            return $this->olxPaymentPriceClient->fetchCurrentPriceByAdId($trackedAd->olx_ad_id);
        } catch (Throwable $initialError) {
            $fallbackOlxAdId = $this->olxListingMetadataExtractor->extractAdIdFromListingPage($trackedAd->listing_url);
            $fallbackPriceSnapshot = $this->olxPaymentPriceClient->fetchCurrentPriceByAdId($fallbackOlxAdId);

            if ($fallbackOlxAdId !== $trackedAd->olx_ad_id) {
                $isFallbackAdIdAlreadyTracked = TrackedAd::query()
                    ->where('olx_ad_id', $fallbackOlxAdId)
                    ->whereKeyNot($trackedAd->id)
                    ->exists();

                if (! $isFallbackAdIdAlreadyTracked) {
                    $trackedAd->forceFill(['olx_ad_id' => $fallbackOlxAdId])->save();
                }
            }

            return $fallbackPriceSnapshot;
        }
    }
}
