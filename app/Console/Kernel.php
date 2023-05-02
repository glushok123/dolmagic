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
        $schedule->command('insales:synchronization')->everyThirtyMinutes(); //синхронизация цен и остатков + удаление дублей || insales
        $schedule->command('sbermegamarket:synchronization')->everyFifteenMinutes(); //синхронизация цен и остатков || sbermegamarket

        $schedule->command('calculate:mrgInterval')->everyTwoHours();
        $schedule->command('calculate:mrgIntervalShort')->everyTenMinutes();
        $schedule->command('calculate:mrg')->dailyAt('01:00');

        //$schedule->command('insales:productsMatches')->dailyAt('19:00');
        //$schedule->command('insales:uploadProducts')->hourly();
        $schedule->command('insales:updateProducts')->twiceDaily(10, 17);
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
