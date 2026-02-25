<?php

use App\Http\Controllers\PriceSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/price-subscriptions', [PriceSubscriptionController::class, 'subscribeToListingPrice']);
