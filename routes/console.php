<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatically expire overdue course enrollments every minute
Schedule::command('enrollments:expire')->everyMinute();

// Notify students in-app 3 days before their course enrollment expires
Schedule::command('enrollments:notify-expiring')->dailyAt('09:00');
