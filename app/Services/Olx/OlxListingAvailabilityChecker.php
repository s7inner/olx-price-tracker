<?php

namespace App\Services\Olx;

use App\Exceptions\ListingPreflightException;
use Illuminate\Http\Client\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class OlxListingAvailabilityChecker
{
    public function assertSubscribableResponse(HttpResponse $listingPageResponse): void
    {
        $statusCode = $listingPageResponse->status();

        match ($listingPageResponse->status()) {
            Response::HTTP_OK => null,
            Response::HTTP_NOT_FOUND => throw ListingPreflightException::notPublic(),
            Response::HTTP_GONE => throw ListingPreflightException::inactive(),
            default => throw ListingPreflightException::unavailable(statusCode: $statusCode),
        };
    }
}
