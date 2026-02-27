<?php

namespace App\Mail;

use App\Enums\ListingNotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->notificationType) {
                ListingNotificationType::PRICE_CHANGED => 'OLX listing price changed',
                ListingNotificationType::LISTING_INACTIVE => 'OLX listing is no longer active',
                ListingNotificationType::LISTING_NON_PUBLIC => 'OLX listing is temporarily non-public',
                ListingNotificationType::LISTING_REACTIVATED => 'OLX listing is active again',
                ListingNotificationType::LISTING_REACTIVATED_WITH_PRICE_CHANGE => 'OLX listing is active again and price changed',
                ListingNotificationType::LISTING_UNAVAILABLE => 'OLX listing is temporarily unavailable',
                ListingNotificationType::SUBSCRIPTION_CREATED => 'You have successfully subscribed to OLX listing updates',
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
