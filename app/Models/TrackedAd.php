<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedAd extends Model
{
    protected $fillable = [
        'olx_ad_id',
        'listing_url',
        'current_price_minor',
        'currency_code',
        'last_checked_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PriceSubscription::class);
    }
}
