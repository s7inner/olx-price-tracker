@php
    use App\Enums\ListingNotificationType;

    $formatPrice = static fn (int $minor, string $currency): string => number_format($minor / 100, 2, '.', ' ').' '.$currency;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OLX listing notification</title>
</head>
<body>
@switch($notificationType)
    @case(ListingNotificationType::PRICE_CHANGED)
        <p>The price of your tracked OLX listing has changed.</p>
        <p>
            Previous price: {{ $formatPrice($previousPriceMinor, $currencyCode) }}<br>
            Current price: {{ $formatPrice($currentPriceMinor, $currencyCode) }}
        </p>
        @break

    @case(ListingNotificationType::SUBSCRIPTION_CREATED)
        <p>You have successfully subscribed to OLX listing price updates.</p>
        <p>
            Current price: {{ $formatPrice($currentPriceMinor, $currencyCode) }}
        </p>
        @break

    @case(ListingNotificationType::LISTING_INACTIVE)
        <p>Your tracked OLX listing is no longer active.</p>
        @break
@endswitch

<p>Listing URL: <a href="{{ $listingUrl }}">{{ $listingUrl }}</a></p>
</body>
</html>
