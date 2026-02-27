<?php

namespace App\Console\Commands;

use App\Enums\ListingNotificationType;
use App\Enums\ListingTrackingStatus;
use App\Jobs\SendListingNotificationEmailJob;
use App\Models\TrackedAd;
use App\Services\Olx\OlxListingPageClient;
use App\Services\Olx\OlxPaymentPriceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackOlxAdPriceChangesCommand extends Command
{
    protected $signature = 'ads:check-olx-prices';

    protected $description = 'Check tracked OLX listings and notify subscribers on price changes.';

    public function __construct(
        private readonly OlxListingPageClient $olxListingPageClient,
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
        $statusContext = $this->syncStatusAndNotifyIfNeeded($trackedAd);
        // 200/4../5.../... -> 4../5..
        if ($statusContext['currentStatus'] !== ListingTrackingStatus::ACTIVE) {
            return;
        }

        $this->processActiveListingPrice(
            trackedAd: $trackedAd,
            wasReactivated: $statusContext['wasReactivated'],
        );
    }

    /**
     * @return array{currentStatus: ListingTrackingStatus, wasReactivated: bool}
     */
    private function syncStatusAndNotifyIfNeeded(TrackedAd $trackedAd): array
    {
        $previousStatus = $trackedAd->status;
        $currentStatus = $this->resolveListingStatus($trackedAd);
        // 4../5.../... -> 200
        $wasReactivated = $previousStatus !== ListingTrackingStatus::ACTIVE
            && $currentStatus === ListingTrackingStatus::ACTIVE;

        if ($currentStatus === $previousStatus) {
            return [
                'currentStatus' => $currentStatus,
                'wasReactivated' => $wasReactivated,
            ];
        }

        $trackedAd->forceFill([
            'status' => $currentStatus,
        ])->save();

        // 200 -> 4../5.../...
        if ($previousStatus === ListingTrackingStatus::ACTIVE) {
            $this->notifyOnStatusTransition($trackedAd, $currentStatus);
        }

        return [
            'currentStatus' => $currentStatus,
            'wasReactivated' => $wasReactivated,
        ];
    }

    private function processActiveListingPrice(TrackedAd $trackedAd, bool $wasReactivated): void
    {
        $currentPriceSnapshot = $this->fetchCurrentPriceSnapshot($trackedAd);
        if ($currentPriceSnapshot === null) {
            return;
        }

        $previousPriceMinor = $trackedAd->current_price_minor;
        $currentPriceMinor = $currentPriceSnapshot['current_price_minor'];
        $priceHasChanged = $currentPriceMinor !== $previousPriceMinor;

        if (! $priceHasChanged) {
            if ($wasReactivated) {
                $this->notifyAllSubscribers($trackedAd, ListingNotificationType::LISTING_REACTIVATED);
            }

            return;
        }

        $trackedAd->forceFill([
            'current_price_minor' => $currentPriceMinor,
            'currency_code' => $currentPriceSnapshot['currency_code'],
        ])->save();

        $notificationType = $wasReactivated
            ? ListingNotificationType::LISTING_REACTIVATED_WITH_PRICE_CHANGE
            : ListingNotificationType::PRICE_CHANGED;

        $this->notifyAllSubscribers(
            trackedAd: $trackedAd,
            notificationType: $notificationType,
            previousPriceMinor: $previousPriceMinor,
            currentPriceMinor: $currentPriceMinor,
            currencyCode: $trackedAd->currency_code,
        );
    }

    /**
     * @return array{current_price_minor: int, currency_code: string}|null
     */
    private function fetchCurrentPriceSnapshot(TrackedAd $trackedAd): ?array
    {
        try {
            return $this->olxPaymentPriceClient->fetchCurrentPriceByAdId($trackedAd->olx_ad_id);
        } catch (Throwable $throwable) {
            Log::warning('Failed to check tracked OLX ad price.', $this->buildLogContext($trackedAd, $throwable));

            return null;
        }
    }

    private function resolveListingStatus(TrackedAd $trackedAd): ListingTrackingStatus
    {
        try {
            $statusCode = $this->olxListingPageClient
                ->fetch($trackedAd->listing_url)
                ->status();
        } catch (Throwable $throwable) {
            Log::warning('Failed to check tracked OLX listing page status.', $this->buildLogContext($trackedAd, $throwable));

            return ListingTrackingStatus::UNAVAILABLE;
        }

        return match ($statusCode) {
            Response::HTTP_OK => ListingTrackingStatus::ACTIVE,
            Response::HTTP_NOT_FOUND => ListingTrackingStatus::NON_PUBLIC,
            Response::HTTP_GONE => ListingTrackingStatus::INACTIVE,
            default => ListingTrackingStatus::UNAVAILABLE,
        };
    }

    private function notifyOnStatusTransition(TrackedAd $trackedAd, ListingTrackingStatus $currentStatus): void
    {
        $notificationType = match ($currentStatus) {
            ListingTrackingStatus::NON_PUBLIC => ListingNotificationType::LISTING_NON_PUBLIC,
            ListingTrackingStatus::INACTIVE => ListingNotificationType::LISTING_INACTIVE,
            ListingTrackingStatus::UNAVAILABLE => ListingNotificationType::LISTING_UNAVAILABLE,
            default => null,
        };

        if ($notificationType !== null) {
            $this->notifyAllSubscribers($trackedAd, $notificationType);
        }
    }

    /**
     * @return array{
     *     tracked_ad_id: int,
     *     olx_ad_id: string,
     *     listing_url: string,
     *     error: string
     * }
     */
    private function buildLogContext(TrackedAd $trackedAd, Throwable $throwable): array
    {
        return [
            'tracked_ad_id' => $trackedAd->id,
            'olx_ad_id' => $trackedAd->olx_ad_id,
            'listing_url' => $trackedAd->listing_url,
            'error' => $throwable->getMessage(),
        ];
    }

    private function notifyAllSubscribers(
        TrackedAd $trackedAd,
        ListingNotificationType $notificationType,
        ?int $previousPriceMinor = null,
        ?int $currentPriceMinor = null,
        ?string $currencyCode = null
    ): void {
        foreach ($trackedAd->subscriptions as $subscription) {
            SendListingNotificationEmailJob::dispatch(
                subscriberEmail: $subscription->subscriber_email,
                listingUrl: $trackedAd->listing_url,
                notificationType: $notificationType,
                previousPriceMinor: $previousPriceMinor,
                currentPriceMinor: $currentPriceMinor,
                currencyCode: $currencyCode,
            );
        }
    }
}
