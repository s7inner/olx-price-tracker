<?php

namespace App\Http\Controllers;

use App\Exceptions\ListingPreflightException;
use App\Http\Requests\StorePriceSubscriptionRequest;
use App\Http\Resources\PriceSubscriptionResource;
use App\Services\PriceSubscription\SubscribeToListingPriceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PriceSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscribeToListingPriceService $subscribeToListingPriceService,
    ) {
    }

    public function subscribeToListingPrice(StorePriceSubscriptionRequest $request): JsonResponse
    {
        try {
            $subscriptionDTO = $this->subscribeToListingPriceService->handle(
                listingUrl: $request->input('listing_url'),
                subscriberEmail: $request->input('subscriber_email'),
            );
        } catch (ListingPreflightException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode->value,
            ], $exception->httpStatus);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Could not process subscription right now. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $responseStatusCode = $subscriptionDTO->isNewSubscription ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new PriceSubscriptionResource($subscriptionDTO))
            ->response()
            ->setStatusCode($responseStatusCode);
    }
}
