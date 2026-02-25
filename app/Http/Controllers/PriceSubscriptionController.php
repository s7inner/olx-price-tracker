<?php

namespace App\Http\Controllers;

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
        } catch (Throwable $throwable) {
            return response()->json([
                'message' => 'Could not subscribe to this listing. Please verify URL and try again.',
                'error' => $throwable->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $responseStatusCode = $subscriptionDTO->isNewSubscription ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new PriceSubscriptionResource($subscriptionDTO))
            ->response()
            ->setStatusCode($responseStatusCode);
    }
}
