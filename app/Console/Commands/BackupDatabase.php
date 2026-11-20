<?php

namespace App\Console\Commands;

use App\Support\BackupFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * データベースのバックアップをとる（R-09・D-044）。
 * 書き出して gzip で小さくし、残す日数より古いバックアップを消す
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'データベースのバックアップをとり、古いものを消す';

    public function handle(): int
    {
        $dir = config('backup.path');
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
