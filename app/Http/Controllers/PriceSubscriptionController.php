<?php

namespace App\Http\Controllers;

use App\Actions\PriceSubscription\SubscribeToListingPriceAction;
use App\Enums\ListingNotificationType;
use App\Exceptions\ListingPreflightException;
use App\Http\Requests\StorePriceSubscriptionRequest;
use App\Http\Resources\PriceSubscriptionResource;
use App\Jobs\SendListingNotificationEmailJob;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PriceSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscribeToListingPriceAction $subscribeToListingPriceAction,
    ) {
    }

    public function subscribeToListingPrice(StorePriceSubscriptionRequest $request): JsonResponse
    {
        try {
            $subscriptionDTO = ($this->subscribeToListingPriceAction)(
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

        if ($subscriptionDTO->isNewSubscription) {
            SendListingNotificationEmailJob::dispatch(
                subscriberEmail: $request->input('subscriber_email'),
                listingUrl: $subscriptionDTO->listingUrl,
                notificationType: ListingNotificationType::SUBSCRIPTION_CREATED,
                currentPriceMinor: $subscriptionDTO->currentPriceMinor,
                currencyCode: $subscriptionDTO->currencyCode,
            );
        }

        return (new PriceSubscriptionResource($subscriptionDTO))
            ->response()
            ->setStatusCode($subscriptionDTO->isNewSubscription ?
                Response::HTTP_CREATED :
                Response::HTTP_OK
            );
    }
}
