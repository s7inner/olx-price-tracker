<?php

use App\Http\Controllers\Auth\EmailAuthController;
use App\Http\Controllers\PriceSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/email/verify/{id}/{hash}', [EmailAuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::name('api.')->group(function (): void {
    Route::post('/auth/email/request-link', [EmailAuthController::class, 'requestVerificationLink'])
        ->middleware('throttle:6,1')
        ->name('auth.email.request-link');

    Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
        Route::post('/price-subscriptions', [PriceSubscriptionController::class, 'subscribeToListingPrice'])
            ->name('price-subscriptions.subscribe');
    });
});
