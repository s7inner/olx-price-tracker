<?php

namespace App\Providers;

use App\Actions\PriceSubscription\SubscribeToListingPriceAction;
use App\Contracts\PriceSubscription\SubscribeToListingPriceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            SubscribeToListingPriceInterface::class,
            SubscribeToListingPriceAction::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
