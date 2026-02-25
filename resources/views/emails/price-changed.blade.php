<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OLX listing price changed</title>
</head>
<body>
    <p>The price of your tracked OLX listing has changed.</p>
    <p>
        Previous price: {{ number_format($previousPriceMinor / 100, 2, '.', ' ') }} {{ $currencyCode }}<br>
        Current price: {{ number_format($currentPriceMinor / 100, 2, '.', ' ') }} {{ $currencyCode }}
    </p>
    <p>Listing URL: <a href="{{ $listingUrl }}">{{ $listingUrl }}</a></p>
</body>
</html>
