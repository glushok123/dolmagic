<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        Log::info('##############################################');
        $schedule->command('insales:synchronization')->everyThirtyMinutes();

        Log::info('##############################################');
        $schedule->command('calculate:mrgInterval')->everyTwoHours();

        Log::info('##############################################');
        $schedule->command('calculate:mrgIntervalShort')->everyTenMinutes();
        
        Log::info('##############################################');
        $schedule->command('calculate:mrg')->dailyAt('01:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
