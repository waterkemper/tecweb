<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('zendesk:sync')
    ->everyThreeMinutes()
    ->withoutOverlapping(5)
    ->name('zendesk-sync-tickets');

Schedule::command('zendesk:sync --users-only')
    ->hourly()
    ->name('zendesk-sync-users');

Schedule::command('zendesk:sync --orgs-only')
    ->hourly()
    ->name('zendesk-sync-orgs');

Schedule::command('zendesk:process-ai --limit=20')
    ->everyTenMinutes()
    ->name('zendesk-process-ai');
