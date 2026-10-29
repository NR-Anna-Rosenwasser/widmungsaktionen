<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('aktionen:sync-status')->everyMinute()->withoutOverlapping();
Schedule::command('orders:send-payment-reminders')->dailyAt('09:00')->timezone('Europe/Zurich');
Schedule::command('orders:send-abandoned-reminders')->hourly()->withoutOverlapping();
Schedule::command('orders:clean-up')->dailyAt('03:30')->timezone('Europe/Zurich');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('address:import-postcodes')->monthlyOn(2, '04:00')->timezone('Europe/Zurich');
