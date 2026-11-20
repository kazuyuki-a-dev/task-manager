<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎日のバックアップ（R-09・D-044・D-045・D-047）。
// 5分ごとに「いちばん近い、すぎたバックアップの時刻（初期値 12:30）より後のバックアップがあるか」を見て、なければとる。
// ふだんは 12:30 にとれる。12:30 に PC が止まっていても、PC が動き出してから5分以内に、とれていなかった分をとる。
// 動かすには、scheduler の箱（compose.yaml）か、毎分「php artisan schedule:run」を動かす設定がいる（docs/operations.md）
Schedule::command('backup:database --if-due')
    ->everyFiveMinutes()
    // 前のバックアップがまだ終わっていなければ、重ねて動かさない
    ->withoutOverlapping();
