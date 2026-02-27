<?php

namespace Database\Factories;

use App\Enums\ListingTrackingStatus;
use App\Models\TrackedAd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackedAd>
 */
class TrackedAdFactory extends Factory
{
    protected $model = TrackedAd::class;

    public function definition(): array
    {
        $token = strtoupper($this->faker->bothify('ID??##??'));

        return [
            'olx_ad_id' => $this->faker->unique()->numerify('########'),
            'listing_url' => "https://www.olx.ua/d/uk/obyavlenie/example-{$token}.html",
            'current_price_minor' => $this->faker->numberBetween(10_000, 2_000_000),
            'currency_code' => 'UAH',
            'status' => ListingTrackingStatus::ACTIVE,
        ];
    }
}
