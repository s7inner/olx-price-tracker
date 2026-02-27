<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracked_ad_id',
        'user_id',
    ];

    public function trackedAd(): BelongsTo
    {
        return $this->belongsTo(TrackedAd::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
