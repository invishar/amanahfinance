<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shared hosting (hPanel) has no persistent process and no pcntl/posix, so
// neither `queue:work` as a daemon nor Horizon can run there. hPanel's cron
// tab needs exactly one entry: `* * * * * php artisan schedule:run`. This
// then bursts a short-lived worker every minute that exits once the queue
// (LLM/OCR/STT jobs -- see CLAUDE.md "Alur AI") drains or 50s pass, well
// under shared hosting's max_execution_time.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Cek masa aktif langganan sekali sehari; sama sekali tidak butuh worker/daemon
// -- satu query bulk UPDATE per burst cron (lihat CLAUDE.md "Perintah").
Schedule::command('amana:expire-subscriptions')->daily();
