<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BackupFiles;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者の画面の入口。いちばん上に、最後のバックアップを出す（R-09・D-046）
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        // お試し用のデモでは、バックアップを止めている（D-048）。「まだバックアップがありません」と赤く出さないように、べつに知らせる
        if (config('demo.enabled')) {
            return Inertia::render('admin/Home', ['backup' => null, 'backupStopped' => true]);
        }

        $latest = BackupFiles::latest(config('backup.path'));

        return Inertia::render('admin/Home', [
            // 1つもなければ null（画面では「まだバックアップがありません」と赤で出す）
            'backup' => $latest === null ? null : [
                'fileName' => $latest['fileName'],
                'takenAt' => $latest['takenAt']->format('Y-m-d H:i'),
                'hoursAgo' => (int) floor($latest['takenAt']->diffInHours(now())),
                // 決めた時間（初期値25時間）より前なら、赤く知らせる
                'isStale' => $latest['takenAt']->lt(now()->subHours(config('backup.stale_hours'))),
            ],
            'backupStopped' => false,
        ]);
    }
}
