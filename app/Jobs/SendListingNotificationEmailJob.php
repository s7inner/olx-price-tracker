<?php

namespace App\Jobs;

use App\Enums\ListingNotificationType;
use App\Mail\ListingNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendListingNotificationEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $subscriberEmail,
        private readonly string $listingUrl,
        private readonly ListingNotificationType $notificationType,
        private readonly ?int $previousPriceMinor = null,
        private readonly ?int $currentPriceMinor = null,
        private readonly ?string $currencyCode = null,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->subscriberEmail)->send(
            new ListingNotificationMail(
                listingUrl: $this->listingUrl,
                notificationType: $this->notificationType,
                previousPriceMinor: $this->previousPriceMinor,
                currentPriceMinor: $this->currentPriceMinor,
                currencyCode: $this->currencyCode,
            )
        );
    }
}
