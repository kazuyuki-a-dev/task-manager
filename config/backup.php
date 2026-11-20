<?php

/*
 * データベースのバックアップ（R-09・D-044）。
 * 動かす機械が決まったら、.env で保存先と残す日数を変える（docs/operations.md）
 */
return [

    // 保存先。外付けディスクや社内の共有フォルダにするときは、.env の BACKUP_PATH に書く
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    // 何日分残すか。これより古いバックアップは、バックアップをとったあとに消す
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

    // 毎日何時にとるか（24時間の「時:分」）。夜中は PC が止まっているので、昼の 12:30（D-045）
    'time' => env('BACKUP_TIME', '12:30'),

    // 最後のバックアップから何時間たったら、管理者の画面で赤く知らせるか（D-046）。
    // 24時間ちょうどにすると、毎日の時刻ちょうどに画面を開いたとき、その日の分がまだ終わっていなくても赤くなるので、少し足す
    'stale_hours' => (int) env('BACKUP_STALE_HOURS', 25),

];
