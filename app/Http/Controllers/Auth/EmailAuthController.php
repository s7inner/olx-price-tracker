<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EmailAuthController extends Controller
{
    public function requestVerificationLink(Request $request): JsonResponse
    {
        $email = mb_strtolower($request->validate(['email' => ['required', 'email']])['email']);

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::before($email, '@'),
                'password' => Hash::make(Str::random(40)),
            ],
        );

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'A verification link has been sent to your email.',
        ], Response::HTTP_ACCEPTED);
    }

    public function verifyEmail(int $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], Response::HTTP_FORBIDDEN);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json([
            'message' => 'Email has been verified successfully.',
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }
}
