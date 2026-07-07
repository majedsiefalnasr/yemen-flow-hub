<?php

use App\Console\Commands\ExpireEngineClaimsCommand;
use App\Console\Commands\NotifySlaSignalsCommand;
use App\Console\Commands\PurgeOldNotificationsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpireEngineClaimsCommand::class)->everyMinute();
Schedule::command(NotifySlaSignalsCommand::class)->hourly();
Schedule::command(PurgeOldNotificationsCommand::class)->dailyAt('02:10');
