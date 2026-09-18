<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Automated 3-hour pre-clinic appointment reminders.
 * Scans every 5 minutes for appointments reaching their 3-hour reminder window.
 */
Schedule::command('bookings:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
