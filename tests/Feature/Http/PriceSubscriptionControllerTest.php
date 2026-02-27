<?php

namespace Tests\Feature\Http;

use App\Contracts\PriceSubscription\SubscribeToListingPriceInterface;
use App\Enums\ListingNotificationType;
use App\Enums\ListingTrackingStatus;
use App\Exceptions\ListingPreflightException;
use App\Jobs\SendListingNotificationEmailJob;
use App\Models\User;
use App\Services\PriceSubscription\SubscriptionDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PriceSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_URL = 'https://www.olx.ua/d/uk/obyavlenie/example-IDAA11BB.html';
    private const EMAIL = 'subscriber@example.com';
    private const CURRENCY_CODE = 'UAH';
    private const CURRENT_PRICE_MINOR = 150_000;
    private const NEW_SUBSCRIPTION_ID = 10;
    private const EXISTING_SUBSCRIPTION_ID = 11;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->user = User::factory()->create([
            'email' => self::EMAIL,
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_it_returns_created_and_dispatches_email_for_new_subscription(): void
    {
        $dto = $this->makeDto(
            subscriptionId: self::NEW_SUBSCRIPTION_ID,
            isNewSubscription: true,
        );

        $this->mock(SubscribeToListingPriceInterface::class, function (MockInterface $mock) use ($dto): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->with(self::LISTING_URL, $this->user->id)
                ->andReturn($dto);
        });

        $response = $this->postSubscription();

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'data' => [
                    'subscription_id' => self::NEW_SUBSCRIPTION_ID,
                    'is_new_subscription' => true,
                    'listing_url' => self::LISTING_URL,
                    'current_price_minor' => self::CURRENT_PRICE_MINOR,
                    'currency_code' => self::CURRENCY_CODE,
                ],
            ]);

        Queue::assertPushed(SendListingNotificationEmailJob::class, 1);
        Queue::assertPushed(SendListingNotificationEmailJob::class, function (SendListingNotificationEmailJob $job): bool {
            return $job->notificationType() === ListingNotificationType::SUBSCRIPTION_CREATED
                && $job->previousPriceMinor() === null
                && $job->currentPriceMinor() === self::CURRENT_PRICE_MINOR;
        });
    }

    public function test_it_returns_ok_and_does_not_dispatch_email_for_existing_subscription(): void
    {
        $dto = $this->makeDto(
            subscriptionId: self::EXISTING_SUBSCRIPTION_ID,
            isNewSubscription: false,
        );

        $this->mock(SubscribeToListingPriceInterface::class, function (MockInterface $mock) use ($dto): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->with(self::LISTING_URL, $this->user->id)
                ->andReturn($dto);
        });

        $response = $this->postSubscription();

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'data' => [
                    'subscription_id' => self::EXISTING_SUBSCRIPTION_ID,
                    'is_new_subscription' => false,
                ],
            ]);

        Queue::assertNothingPushed();
    }

    #[DataProvider('actionFailureProvider')]
    public function test_it_returns_expected_error_payload_when_action_throws_exception(
        \Throwable $exception,
        int $expectedStatusCode,
        array $expectedJson
    ): void
    {
        $this->mock(SubscribeToListingPriceInterface::class, function (MockInterface $mock) use ($exception): void {
            $mock->shouldReceive('__invoke')
                ->once()
                ->with(self::LISTING_URL, $this->user->id)
                ->andThrow($exception);
        });

        $response = $this->postSubscription();

        $response->assertStatus($expectedStatusCode)
            ->assertJson($expectedJson);

        Queue::assertNothingPushed();
    }

    public static function actionFailureProvider(): array
    {
        return [
            'listing preflight exception' => [
                ListingPreflightException::inactive(),
                Response::HTTP_GONE,
                [
                    'message' => 'Listing is inactive or deleted.',
                    'error_code' => ListingTrackingStatus::INACTIVE->value,
                ],
            ],
            'unexpected exception' => [
                new \RuntimeException(),
                Response::HTTP_SERVICE_UNAVAILABLE,
                [],
            ],
        ];
    }

    public function test_it_returns_validation_errors_for_invalid_payload(): void
    {
        $this->mock(SubscribeToListingPriceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('__invoke')->never();
        });

        $response = $this->postSubscription([
            'listing_url' => 'http://example.com/not-olx',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'listing_url',
            ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postSubscription(array $payload = []): TestResponse
    {
        return $this->postJson(route('api.price-subscriptions.subscribe'), array_replace([
            'listing_url' => self::LISTING_URL,
        ], $payload));
    }

    private function makeDto(int $subscriptionId, bool $isNewSubscription): SubscriptionDTO
    {
        return new SubscriptionDTO(
            subscriptionId: $subscriptionId,
            isNewSubscription: $isNewSubscription,
            listingUrl: self::LISTING_URL,
            currentPriceMinor: self::CURRENT_PRICE_MINOR,
            currencyCode: self::CURRENCY_CODE,
        );
    }
}
