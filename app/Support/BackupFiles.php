<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * バックアップのファイルを見つける（R-09・D-046）。
 * ファイルの名前（task-manager-年月日-時分秒.sql.gz）から日時を読む。
 * バックアップをとるコマンドと、管理者の画面の両方で使う
 */
class BackupFiles
{
    /**
     * バックアップのファイルの名前の形。この形のものだけをバックアップとして扱う
     */
    public const FILE_PATTERN = '/^task-manager-(\d{8}-\d{6})\.sql\.gz$/';

    /**
     * フォルダの中のバックアップを、名前の日時と一緒に返す。名前の形でないファイルは入れない
     *
     * @return list<array{path: string, fileName: string, takenAt: Carbon}>
     */
    public static function all(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }

        $backups = [];
        foreach (File::files($dir) as $file) {
            $takenAt = self::takenAt($file->getFilename());
            if ($takenAt !== null) {
                $backups[] = ['path' => $file->getPathname(), 'fileName' => $file->getFilename(), 'takenAt' => $takenAt];
            }
        }

        return $backups;
    }

    /**
     * いちばん新しいバックアップ（名前の日時で比べる）。1つもなければ null
     *
     * @return array{path: string, fileName: string, takenAt: Carbon}|null
     */
    public static function latest(string $dir): ?array
    {
        $latest = null;
        foreach (self::all($dir) as $backup) {
            if ($latest === null || $backup['takenAt']->gt($latest['takenAt'])) {
                $latest = $backup;
            }
        }

        return $latest;
    }

    /**
     * ファイルの名前から、バックアップをとった日時を読む。バックアップの名前の形でなければ null
     */
    public static function takenAt(string $fileName): ?Carbon
    {
        if (preg_match(self::FILE_PATTERN, $fileName, $match) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Ymd-His', $match[1]);
    }
}
