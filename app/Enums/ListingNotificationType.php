<?php

namespace App\Enums;

enum ListingNotificationType: string
{
    case PRICE_CHANGED = 'price_changed';
    case LISTING_INACTIVE = 'listing_inactive';
    case LISTING_NON_PUBLIC = 'listing_non_public';
    case LISTING_REACTIVATED = 'listing_reactivated';
    case LISTING_REACTIVATED_WITH_PRICE_CHANGE = 'listing_reactivated_with_price_change';
    case LISTING_UNAVAILABLE = 'listing_unavailable';
    case SUBSCRIPTION_CREATED = 'subscription_created';
}
