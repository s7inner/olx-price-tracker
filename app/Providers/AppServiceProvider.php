<?php

namespace App\Providers;

use App\Actions\PriceSubscription\SubscribeToListingPriceAction;
use App\Contracts\PriceSubscription\SubscribeToListingPriceInterface;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
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
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
                $openApi->info->description = 'OLX Price Tracker API – subscribe to listing price changes and receive email notifications when prices drop. Authentication uses Bearer tokens (obtained via email verification). Rate limit: 6 requests per minute on auth endpoints.';
            });
    }
}
