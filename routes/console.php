<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:process-recurring')->dailyAt('09:00');
Schedule::command('invoices:send-reminders')->dailyAt('12:00');
Schedule::command('invoices:cancel-expired')->weeklyOn(1, '03:00');
