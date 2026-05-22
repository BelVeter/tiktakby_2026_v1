<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
        $schedule->command('sitemap:generate')->weekly();

        // A1 ВАТС: пропущенные звонки
        // В рабочее время (9:00-19:00) — каждые 10 минут (период выборки 15 мин с перекрытием)
        $schedule->command('a1:fetch-missed-calls', ['--period' => 15])
            ->everyTenMinutes()
            ->between('9:00', '19:00')
            ->withoutOverlapping();

        // Вне рабочего времени — раз в час
        $schedule->command('a1:fetch-missed-calls', ['--period' => 70])
            ->hourly()
            ->unlessBetween('9:00', '19:00')
            ->withoutOverlapping();

        // Записи звонков — каждый час в :05 (не пересекается с пропущенными :00,:10,...,:50)
        $schedule->command('a1:fetch-recordings')
            ->hourlyAt(5)
            ->withoutOverlapping();

        // CDR (история всех звонков) — каждый час в :15
        $schedule->command('a1:fetch-cdr', ['--period' => 90])
            ->hourlyAt(15)
            ->withoutOverlapping();
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
