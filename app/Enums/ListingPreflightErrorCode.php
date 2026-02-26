<?php

namespace App\Enums;

enum ListingPreflightErrorCode: string
{
    case NOT_PUBLIC = 'not_public';
    case INACTIVE = 'inactive';
    case UNAVAILABLE = 'unavailable';
}
