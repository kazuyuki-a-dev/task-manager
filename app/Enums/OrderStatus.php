<?php

namespace App\Enums;

/**
 * 製造指示の状態。ボードの列の順番も、この並びのとおり
 */
enum OrderStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * この状態から、次の状態に変えてよいか（R-04・D-024・D-025）。
     * 未着手 → 作業中 → 完了 の順に進める。まちがえたときのために、1つ前には戻せる
     */
    public function canChangeTo(self $next): bool
    {
        return match ($this) {
            self::NotStarted => $next === self::InProgress,
            self::InProgress => $next === self::Done || $next === self::NotStarted,
            self::Done => $next === self::InProgress,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => '未着手',
            self::InProgress => '作業中',
            self::Done => '完了',
        };
    }
}
