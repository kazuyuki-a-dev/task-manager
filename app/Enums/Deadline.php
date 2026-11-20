<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * 未完了の指示の、納期に対する状態（R-02・D-008）
 */
enum Deadline: string
{
    /** 納期の翌日から */
    case Overdue = 'overdue';

    /** 納期の2日前から当日まで */
    case Soon = 'soon';

    /**
     * 納期の何日前から「期限間近」にするか
     */
    public const SOON_DAYS = 2;

    /**
     * 納期と今日から、状態を決める。どちらでもなければ null
     *
     * @param  int  $daysLeft  納期まであと何日か（今日が納期なら 0、1日過ぎたら -1）
     */
    public static function fromDaysLeft(int $daysLeft): ?self
    {
        return match (true) {
            $daysLeft < 0 => self::Overdue,
            $daysLeft <= self::SOON_DAYS => self::Soon,
            default => null,
        };
    }

    /**
     * 納期まであと何日か。時刻は見ずに、日付だけで数える
     */
    public static function daysLeft(CarbonInterface $dueDate, CarbonInterface $today): int
    {
        return (int) $today->copy()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);
    }

    public function label(): string
    {
        return match ($this) {
            self::Overdue => '遅れ',
            self::Soon => '期限間近',
        };
    }
}
