<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EmailAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_user_and_sends_verification_link(): void
    {
        Notification::fake();

        $email = 'new-user@example.com';
        $response = $this->postJson(route('api.auth.email.request-link'), ['email' => $email]);

        $response->assertStatus(Response::HTTP_ACCEPTED);

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_it_verifies_email_and_returns_api_token(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            name: 'verification.verify',
            expiration: now()->addMinutes(60),
            parameters: [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $response = $this->getJson($verificationUrl);

        $response->assertOk()
            ->assertJson([
                'message' => 'Email has been verified successfully.',
                'token_type' => 'Bearer',
            ])
            ->assertJsonStructure(['token']);

        $this->assertNotNull($user->refresh()->email_verified_at);
    }
}
