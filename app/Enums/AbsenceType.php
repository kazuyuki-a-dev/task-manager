<?php

namespace App\Enums;

/**
 * 社員の休みの種類（D-020）。区別は管理者の画面でだけ見える
 */
enum AbsenceType: string
{
    case Paid = 'paid';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Paid => '有給',
            self::Absent => '欠勤',
        };
    }
}
