<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('projects:auto-archive')->dailyAt('00:15');
Schedule::command('projects:create-scheduled')->dailyAt('00:20');

// Daily emails, only to users who opted in (profile > Email Preferences).
// Times are in APP_TIMEZONE.
Schedule::command('email:task-digest --all')->dailyAt('06:00');
Schedule::command('email:task-png --all')->dailyAt('06:00');
