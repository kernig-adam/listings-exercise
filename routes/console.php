<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Overlap is blocked so a slow run can't process the same message twice at once.
Schedule::command('outbox:relay')->everyMinute()->withoutOverlapping();
