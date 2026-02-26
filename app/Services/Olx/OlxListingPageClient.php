<?php

namespace App\Services\Olx;

use App\Exceptions\ListingPreflightException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OlxListingPageClient
{
    public function fetch(string $listingUrl): Response
    {
        try {
            return Http::accept('text/html')
                ->timeout(config('olx.http.timeout_seconds'))
                ->retry(
                    config('olx.http.retry_times'),
                    config('olx.http.retry_sleep_ms')
                )
                ->get($listingUrl);
        } catch (ConnectionException $throwable) {
            throw ListingPreflightException::unavailable(previous: $throwable);
        }
    }
}
