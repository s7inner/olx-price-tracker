<?php

namespace Tests\Unit\Services\Olx;

use App\Enums\ListingTrackingStatus;
use App\Exceptions\ListingPreflightException;
use App\Services\Olx\OlxListingAvailabilityChecker;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response as HttpClientResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OlxListingAvailabilityCheckerTest extends TestCase
{
    private OlxListingAvailabilityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = new OlxListingAvailabilityChecker();
    }

    public function test_it_accepts_ok_status(): void
    {
        $this->checker->assertSubscribableResponse($this->makeResponse(Response::HTTP_OK));

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsupportedStatusProvider')]
    public function test_it_throws_expected_preflight_exception_for_unsupported_status(
        int $statusCode,
        ListingTrackingStatus $expectedErrorCode,
        int $expectedHttpStatus,
        ?string $expectedMessageContains = null
    ): void
    {
        try {
            $this->checker->assertSubscribableResponse($this->makeResponse($statusCode));
            $this->fail('ListingPreflightException was not thrown.');
        } catch (ListingPreflightException $exception) {
            $this->assertSame($expectedErrorCode, $exception->errorCode);
            $this->assertSame($expectedHttpStatus, $exception->httpStatus);

            if ($expectedMessageContains !== null) {
                $this->assertStringContainsString($expectedMessageContains, $exception->getMessage());
            }
        }
    }

    public static function unsupportedStatusProvider(): array
    {
        return [
            '404 not found => non public' => [
                Response::HTTP_NOT_FOUND,
                ListingTrackingStatus::NON_PUBLIC,
                Response::HTTP_NOT_FOUND,
            ],
            '410 gone => inactive' => [
                Response::HTTP_GONE,
                ListingTrackingStatus::INACTIVE,
                Response::HTTP_GONE,
            ],
            '500 internal error => unavailable' => [
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ListingTrackingStatus::UNAVAILABLE,
                Response::HTTP_SERVICE_UNAVAILABLE,
                'status: 500',
            ],
        ];
    }

    private function makeResponse(int $statusCode): HttpClientResponse
    {
        return new HttpClientResponse(new Psr7Response($statusCode));
    }
}
