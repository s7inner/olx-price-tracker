@php use App\Enums\ListingNotificationType; @endphp
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OLX listing notification</title>
</head>
<body>
@if ($notificationType === ListingNotificationType::PRICE_CHANGED)
    <p>The price of your tracked OLX listing has changed.</p>
    <p>
        Previous price: {{ number_format($previousPriceMinor / 100, 2, '.', ' ') }} {{ $currencyCode }}<br>
        Current price: {{ number_format($currentPriceMinor / 100, 2, '.', ' ') }} {{ $currencyCode }}
    </p>
@elseif ($notificationType === ListingNotificationType::LISTING_INACTIVE)
    <p>Your tracked OLX listing is no longer active.</p>
@else
    <p>There is an update for your tracked OLX listing.</p>
@endif

<p>Listing URL: <a href="{{ $listingUrl }}">{{ $listingUrl }}</a></p>
</body>
</html>
