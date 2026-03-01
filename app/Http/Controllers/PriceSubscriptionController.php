<?php

namespace App\Http\Controllers;

use App\Contracts\PriceSubscription\SubscribeToListingPriceInterface;
use App\Enums\ListingNotificationType;
use App\Exceptions\ListingPreflightException;
use App\Http\Requests\StorePriceSubscriptionRequest;
use App\Http\Resources\PriceSubscriptionResource;
use App\Jobs\SendListingNotificationEmailJob;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PriceSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscribeToListingPriceInterface $subscribeToListingPrice,
    ) {
    }

    /**
     * Subscribe to listing price changes.
     */
    #[BodyParameter('listing_url', description: 'OLX listing URL', required: true, type: 'string', format: 'uri', example: 'https://www.olx.ua/d/uk/listing/123456789')]
    #[ScrambleResponse(Response::HTTP_OK, 'OK', type: '\App\Http\Resources\PriceSubscriptionResource')]
    #[ScrambleResponse(Response::HTTP_CREATED, 'New subscription created', type: '\App\Http\Resources\PriceSubscriptionResource')]
    #[ScrambleResponse(Response::HTTP_NOT_FOUND, 'Listing is not publicly available', type: 'array{message: string, error_code: string}')]
    #[ScrambleResponse(Response::HTTP_GONE, 'Listing is inactive or deleted', type: 'array{message: string, error_code: string}')]
    #[ScrambleResponse(Response::HTTP_SERVICE_UNAVAILABLE, 'Service unavailable', type: 'array{message: string}')]
    public function subscribeToListingPrice(StorePriceSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $subscriptionDTO = ($this->subscribeToListingPrice)(
                listingUrl: $request->input('listing_url'),
                userId: $user->id,
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
                subscriberEmail: $user->email,
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
