<?php

namespace App\Enums;

enum ListingNotificationType: string
{
    case PRICE_CHANGED = 'price_changed';
    case LISTING_INACTIVE = 'listing_inactive';
    case SUBSCRIPTION_CREATED = 'subscription_created';
}
