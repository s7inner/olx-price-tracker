<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceChangedNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $listingUrl,
        public readonly int $previousPriceMinor,
        public readonly int $currentPriceMinor,
        public readonly string $currencyCode,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OLX listing price changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.price-changed',
        );
    }
}
