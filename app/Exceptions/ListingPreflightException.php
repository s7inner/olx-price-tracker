<?php

namespace App\Exceptions;

use App\Enums\ListingTrackingStatus;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ListingPreflightException extends RuntimeException
{
    public function __construct(
        public readonly ListingTrackingStatus $errorCode,
        public readonly int $httpStatus,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function notPublic(): self
    {
        return new self(
            errorCode: ListingTrackingStatus::NON_PUBLIC,
            httpStatus: Response::HTTP_NOT_FOUND,
            message: 'Listing is not publicly available (pending, rejected, or blocked).',
        );
    }

    public static function inactive(): self
    {
        return new self(
            errorCode: ListingTrackingStatus::INACTIVE,
            httpStatus: Response::HTTP_GONE,
            message: 'Listing is inactive or deleted.',
        );
    }

    public static function unavailable(?int $statusCode = null, ?Throwable $previous = null): self
    {
        $message = 'Could not verify listing status right now. Please try again later.';
        if ($statusCode !== null) {
            $message = "Could not verify listing status right now (status: {$statusCode}).";
        }

        return new self(
            errorCode: ListingTrackingStatus::UNAVAILABLE,
            httpStatus: Response::HTTP_SERVICE_UNAVAILABLE,
            message: $message,
            previous: $previous,
        );
    }
}
