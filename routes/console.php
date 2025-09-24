<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('agents:check-heartbeats')->everyMinute();
Schedule::command('metrics:cleanup-old')->daily();
Schedule::command('logs:cleanup-old')->daily();