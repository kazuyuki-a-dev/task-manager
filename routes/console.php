<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎日決まった時刻に、データベースのバックアップをとる（R-09・D-044）。
// 動かす機械で、毎分「php artisan schedule:run」を動かす設定が1つだけいる（docs/operations.md）
Schedule::command('backup:database')
    ->dailyAt(config('backup.time'))
    // 前の日のバックアップがまだ終わっていなければ、重ねて動かさない
    ->withoutOverlapping();
