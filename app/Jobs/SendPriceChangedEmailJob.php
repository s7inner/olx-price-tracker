<?php

namespace App\Jobs;

use App\Mail\PriceChangedNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPriceChangedEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $subscriberEmail,
        private readonly string $listingUrl,
        private readonly int $previousPriceMinor,
        private readonly int $currentPriceMinor,
        private readonly string $currencyCode,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->subscriberEmail)->send(
            new PriceChangedNotificationMail(
                listingUrl: $this->listingUrl,
                previousPriceMinor: $this->previousPriceMinor,
                currentPriceMinor: $this->currentPriceMinor,
                currencyCode: $this->currencyCode,
            )
        );
    }
}
