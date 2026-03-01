<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ads:check-olx-prices')
    ->everyFiveMinutes()
    ->withoutOverlapping();
