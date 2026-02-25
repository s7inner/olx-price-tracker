<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSubscription extends Model
{
    protected $fillable = [
        'tracked_ad_id',
        'subscriber_email',
    ];

    public function trackedAd(): BelongsTo
    {
        return $this->belongsTo(TrackedAd::class);
    }
}
