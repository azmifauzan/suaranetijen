<?php

use App\Domains\Ingestion\Jobs\ExpireRawPayloadJob;
use App\Domains\Sources\Models\UnmatchedMention;
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
Schedule::command('landing-pages:scan-candidates')->weekly()->withoutOverlapping();
Schedule::command('entities:enrich-websites')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('entities:fetch-review-videos')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('themes:extract-pending')->everySixHours()->withoutOverlapping();
Schedule::command('themes:summarize')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('search:rebuild-documents')->dailyAt('04:30')->withoutOverlapping();
Schedule::job(new ExpireRawPayloadJob)->everyTwoMinutes();
Schedule::command('model:prune', ['--model' => [UnmatchedMention::class]])->dailyAt('05:00')->withoutOverlapping();
Schedule::command('queue:prune-failed', ['--hours' => 168])->dailyAt('05:15')->withoutOverlapping();
