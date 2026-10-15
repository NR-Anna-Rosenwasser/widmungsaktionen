<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('aktionen:sync-status')->everyMinute()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
