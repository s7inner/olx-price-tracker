<?php

namespace App\Mail;

use App\Enums\ListingNotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class ListingNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $listingUrl,
        public readonly ListingNotificationType $notificationType,
        public readonly ?int $previousPriceMinor = null,
        public readonly ?int $currentPriceMinor = null,
        public readonly ?string $currencyCode = null,
    ) {
        if (
            $this->notificationType === ListingNotificationType::PRICE_CHANGED
            && ($this->previousPriceMinor === null || $this->currentPriceMinor === null || $this->currencyCode === null)
        ) {
            throw new RuntimeException('Price-changed notification requires previous/current price and currency.');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->notificationType) {
                ListingNotificationType::PRICE_CHANGED => 'OLX listing price changed',
                ListingNotificationType::LISTING_INACTIVE => 'OLX listing is no longer active',
                default => 'OLX listing notification',
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.listing-notification',
        );
    }
}
