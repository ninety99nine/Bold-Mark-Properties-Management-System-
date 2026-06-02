<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\RecalculateUnitBalances::class,
        \App\Console\Commands\RunScheduledBilling::class,
        \App\Console\Commands\SendPaymentReminders::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        // Run billing for all estates whose billing_day matches today.
        // Fires at 06:00 Africa/Johannesburg — before the business day starts.
        $schedule->command('billing:run-scheduled')
                 ->dailyAt('06:00')
                 ->timezone('Africa/Johannesburg')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/billing-scheduler.log'));

        // Send payment reminders for overdue unpaid invoices.
        // Fires at 08:00 Africa/Johannesburg — after billing runs.
        $schedule->command('billing:send-payment-reminders')
                 ->dailyAt('08:00')
                 ->timezone('Africa/Johannesburg')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/payment-reminders.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
