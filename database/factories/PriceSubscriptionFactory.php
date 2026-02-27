<?php

namespace Database\Factories;

use App\Models\PriceSubscription;
use App\Models\TrackedAd;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceSubscription>
 */
class PriceSubscriptionFactory extends Factory
{
    protected $model = PriceSubscription::class;

    public function definition(): array
    {
        return [
            'tracked_ad_id' => TrackedAd::factory(),
            'user_id' => User::factory(),
        ];
    }
}
