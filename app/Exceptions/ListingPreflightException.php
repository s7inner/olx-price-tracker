<?php

namespace App\Exceptions;

use App\Enums\ListingPreflightErrorCode;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ListingPreflightException extends RuntimeException
{
    public function __construct(
        public readonly ListingPreflightErrorCode $errorCode,
        public readonly int $httpStatus,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function notPublic(): self
    {
        return new self(
            errorCode: ListingPreflightErrorCode::NOT_PUBLIC,
            httpStatus: Response::HTTP_NOT_FOUND,
            message: 'Listing is not publicly available (pending, rejected, or blocked).',
        );
    }

    public static function inactive(): self
    {
        return new self(
            errorCode: ListingPreflightErrorCode::INACTIVE,
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
            errorCode: ListingPreflightErrorCode::UNAVAILABLE,
            httpStatus: Response::HTTP_SERVICE_UNAVAILABLE,
            message: $message,
            previous: $previous,
        );
    }
}
