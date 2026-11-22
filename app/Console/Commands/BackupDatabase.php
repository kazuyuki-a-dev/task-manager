<?php

namespace App\Console\Commands;

use App\Support\BackupFiles;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * データベースのバックアップをとる（R-09・D-044・D-047）。
 * 書き出して gzip で小さくし、残す日数より古いバックアップを消す。
 * --if-due を付けると、いちばん近い、すぎたバックアップの時刻（初期値 12:30）より後のバックアップがないときだけとる。
 * 12:30 に PC が止まっていても、PC が動き出してから、とれていなかった分をとるため
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database
        {--if-due : いちばん近い、すぎたバックアップの時刻より後のバックアップがないときだけとる}';

    protected $description = 'データベースのバックアップをとり、古いものを消す';

    public function handle(): int
    {
        // お試し用のデモでは止めている（D-048）
        if (config('demo.enabled')) {
            $this->info('お試し用のデモでは、バックアップを止めています');

            return self::SUCCESS;
        }

        $dir = config('backup.path');

        if ($this->option('if-due')) {
            $due = self::lastDueTime(now(), config('backup.time'));
            $latest = BackupFiles::latest($dir);
            if ($latest !== null && $latest['takenAt']->gte($due)) {
                $this->info("もうとれています（{$due->format('m/d H:i')} より後のバックアップ：{$latest['fileName']}）");

                return self::SUCCESS;
            }
        }

        File::ensureDirectoryExists($dir);

        $file = $dir.'/task-manager-'.now()->format('Ymd-His').'.sql.gz';
        $db = config('database.connections.'.config('database.default'));

        // 書き出しているあいだも、ボードを止めずに使えるように --single-transaction を付ける。
        // パスワードは引数に書かず、環境変数で渡す（ほかの人から見えないように）。
        // pipefail を付けて、書き出しが失敗したら gzip がうまくいっても失敗として扱う
        $result = Process::env(['MYSQL_PWD' => (string) $db['password']])
            ->timeout(600)
            ->run([
                'bash', '-o', 'pipefail', '-c',
                'mysqldump --single-transaction --no-tablespaces --host="$1" --port="$2" --user="$3" "$4" | gzip > "$5"',
                'backup', $db['host'], (string) $db['port'], $db['username'], $db['database'], $file,
            ]);

        if ($result->failed()) {
            File::delete($file);
            Log::error('データベースのバックアップに失敗しました', ['error' => $result->errorOutput()]);
            $this->error('バックアップに失敗しました：'.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info('バックアップをとりました：'.$file);

        $deleted = $this->deleteOldBackups($dir);
        if ($deleted > 0) {
            $this->info("古いバックアップを{$deleted}件消しました");
        }

        return self::SUCCESS;
    }

    /**
     * いちばん近い、すぎたバックアップの時刻。今が今日の時刻（例：12:30）より前なら、きのうのその時刻
     */
    public static function lastDueTime(CarbonInterface $now, string $time): CarbonInterface
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $today = $now->copy()->setTime($hour, $minute);

        return $now->lt($today) ? $today->subDay() : $today;
    }

    /**
     * 残す日数より古いバックアップを消す。日時はファイルの名前から読む（BackupFiles）。
     * バックアップの名前の形でないファイルは、さわらない
     */
    private function deleteOldBackups(string $dir): int
    {
        $limit = now()->subDays(config('backup.keep_days'));
        $deleted = 0;

        foreach (BackupFiles::all($dir) as $backup) {
            if ($backup['takenAt']->lt($limit)) {
                File::delete($backup['path']);
                $deleted++;
            }
        }

        return $deleted;
    }
}
