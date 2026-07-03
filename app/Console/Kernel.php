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
        $schedule->command('sitemap:generate')->dailyAt('02:00');

        $schedule->command('feed:generate-2gis')
            ->dailyAt('03:00')
            ->withoutOverlapping();

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

        // Догоняющий прогон записей — раз в сутки широким окном (3 суток).
        // Часовой прогон со скользящим окном --period=90 не умеет добирать
        // записи старше 90 минут, если несколько запусков подряд упали
        // (лимиты/авторизация A1). Этот прогон пере-сканирует record/list за
        // 72 часа и докачивает только отсутствующие (дедуп по record_name),
        // закрывая пропуски без ручного backfill. 04:35 — низкий трафик, не
        // пересекается с часовыми :05/:15 и пропущенными :00/:10/...
        $schedule->command('a1:fetch-recordings', ['--period' => 4320])
            ->dailyAt('04:35')
            ->withoutOverlapping();

        // Геокодирование: активные клиенты — каждые 30 минут
        $schedule->command('geo:geocode-clients', ['--mode' => 'regular'])
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        // Геокодирование: добор квоты — 28-го числа каждого месяца в 03:00
        $schedule->command('geo:geocode-clients', ['--mode' => 'quota-filler'])
            ->monthlyOn(28, '03:00')
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
