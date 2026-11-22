<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * バックアップからデータベースを戻す（R-09・D-044）。戻し方は docs/operations.md
 */
class RestoreDatabase extends Command
{
    protected $signature = 'backup:restore
        {file : 戻すバックアップのファイル（保存先のフォルダの中の名前、または場所つきの名前）}
        {--force : 確かめずに戻す（戻す試しのとき用）}';

    protected $description = 'バックアップからデータベースを戻す（今のデータは消える）';

    public function handle(): int
    {
        // お試し用のデモでは止めている（D-048）
        if (config('demo.enabled')) {
            $this->info('お試し用のデモでは、バックアップから戻すのを止めています');

            return self::SUCCESS;
        }

        $file = $this->argument('file');
        // 名前だけのときは、保存先のフォルダの中をさがす
        if (! File::exists($file)) {
            $file = config('backup.path').'/'.$file;
        }
        if (! File::exists($file)) {
            $this->error('バックアップのファイルが見つかりません：'.$this->argument('file'));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("{$file} から戻します。今のデータは消えます。よいですか？")) {
            $this->info('戻すのをやめました');

            return self::FAILURE;
        }

        $db = config('database.connections.'.config('database.default'));
        $result = Process::env(['MYSQL_PWD' => (string) $db['password']])
            ->timeout(600)
            ->run([
                'bash', '-o', 'pipefail', '-c',
                'gunzip -c "$5" | mysql --host="$1" --port="$2" --user="$3" "$4"',
                'restore', $db['host'], (string) $db['port'], $db['username'], $db['database'], $file,
            ]);

        if ($result->failed()) {
            $this->error('戻すのに失敗しました：'.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info('戻しました：'.$file);

        return self::SUCCESS;
    }
}
