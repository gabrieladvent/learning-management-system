<?php

use App\Jobs\SendAssignmentDeadlineReminders;
use App\Jobs\SendExamStartReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('exam:auto-submit-expired')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SendAssignmentDeadlineReminders)
    ->dailyAt('06:30')
    ->onOneServer();

Schedule::job(new SendExamStartReminders)
    ->everyFifteenMinutes()
    ->onOneServer();

// Cleanup activity log lama (default 365 hari, atur di config/activitylog.php
// 'clean_after_days'). Run weekly supaya tabel activity_log tidak meledak
// di production yang aktif (80 siswa × 5 material/hari = 400 row/hari).
Schedule::command('activitylog:clean')
    ->weekly()
    ->onOneServer();
