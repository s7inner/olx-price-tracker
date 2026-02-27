<?php

namespace App\Enums;

enum ListingTrackingStatus: string
{
    case ACTIVE = 'active';
    case NON_PUBLIC = 'not_public';
    case INACTIVE = 'inactive';
    case UNAVAILABLE = 'unavailable';
}
