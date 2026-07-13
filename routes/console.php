<?php

use App\Models\AccessLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Flag workflow steps whose SLA has elapsed so assignees get a reminder.
Schedule::command('workflow:escalate')->hourly();

// Prune access-log rows past their retention window (ACCESS_LOG_RETENTION_DAYS).
Schedule::command('model:prune', ['--model' => [AccessLog::class]])->daily();
