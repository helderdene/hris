<?php

use App\Jobs\CheckOverduePreboardingJob;
use App\Jobs\ExpireCarryOverBalancesJob;
use App\Jobs\MarkOfflineDevicesJob;
use App\Jobs\MonthlyLeaveAccrualJob;
use App\Jobs\ScheduledBatchSyncJob;
use App\Jobs\YearEndLeaveProcessingJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
|
| Here you may define all of your scheduled jobs. Jobs will be executed
| at the intervals specified.
|
*/

// Biometric device heartbeat staleness check - every 2 minutes
Schedule::job(new MarkOfflineDevicesJob)
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->name('mark-offline-devices');

// Biometric device sync - every 15 minutes
Schedule::job(new ScheduledBatchSyncJob)->everyFifteenMinutes()->withoutOverlapping();

// Leave balance monthly accrual - 1st of each month at 00:30
Schedule::job(new MonthlyLeaveAccrualJob)
    ->monthlyOn(1, '00:30')
    ->withoutOverlapping()
    ->name('monthly-leave-accrual');

// Leave balance year-end processing - January 1st at 01:00
Schedule::job(new YearEndLeaveProcessingJob(now()->subYear()->year))
    ->yearlyOn(1, 1, '01:00')
    ->withoutOverlapping()
    ->name('year-end-leave-processing');

// Leave balance carry-over expiry check - daily at 02:00
Schedule::job(new ExpireCarryOverBalancesJob)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->name('expire-carry-over-balances');

// Preboarding overdue check - daily at 06:00
Schedule::job(new CheckOverduePreboardingJob)
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->name('check-overdue-preboarding');

// Certification expiry reminders - daily at 07:00
Schedule::command('certifications:send-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->name('certification-expiry-reminders');

// Mark expired certifications - daily at 00:15
Schedule::command('certifications:mark-expired --notify')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->name('mark-expired-certifications');

/*
|--------------------------------------------------------------------------
| Compliance Training Scheduled Commands
|--------------------------------------------------------------------------
*/

// Compliance auto-reassign - daily at 07:00
Schedule::command('compliance:auto-reassign')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->name('compliance-auto-reassign');

// Compliance due reminders - daily at 08:00
Schedule::command('compliance:send-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->name('compliance-due-reminders');

// Process overdue compliance - daily at 09:00
Schedule::command('compliance:process-overdue')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->name('compliance-process-overdue');

// Expire compliance training - daily at 00:30
Schedule::command('compliance:expire-training')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->name('compliance-expire-training');
