<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ABB CBS harita kareleri: arka planda önceden indir (kullanıcı talepleri,
// taşınmaz çevreleri, Ankara merkezi) — kareler web sunucusundan milisaniyede gelir.
Schedule::command('abb:onbellek-isit --sure=55')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// 30 günden eski kareleri sil; ısıtıcı tazesini indirir (imar planı değişikliği yansısın).
Schedule::command('abb:onbellek-temizle')
    ->dailyAt('03:30')
    ->withoutOverlapping();
