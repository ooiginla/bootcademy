<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $disk = Storage::disk('local');
    foreach ($disk->allFiles('imports') as $path) {
        if ($disk->lastModified($path) < now()->subHours(25)->timestamp) {
            $disk->delete($path);
        }
    }
})->hourly()->name('delete-expired-player-imports')->withoutOverlapping();
