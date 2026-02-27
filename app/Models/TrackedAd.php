<?php

namespace App\Models;

use App\Enums\ListingTrackingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedAd extends Model
{
    protected $fillable = [
        'olx_ad_id',
        'listing_url',
        'current_price_minor',
        'currency_code',
        'status',
    ];

    protected $casts = [
        'current_price_minor' => 'integer',
        'status' => ListingTrackingStatus::class,
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PriceSubscription::class);
    }
}
