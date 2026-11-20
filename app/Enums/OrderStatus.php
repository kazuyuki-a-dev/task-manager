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

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => '未着手',
            self::InProgress => '作業中',
            self::Done => '完了',
        };
    }
}
