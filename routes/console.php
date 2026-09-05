<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sources:preflight')->daily()->withoutOverlapping();
Schedule::command('sources:backfill')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:database', ['--verify' => true])->monthlyOn(1, '03:00')->withoutOverlapping();
Schedule::command('monitor:metrics')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('entities:scan-candidates')->weekly()->withoutOverlapping();
