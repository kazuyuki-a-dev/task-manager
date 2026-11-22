<?php

namespace App\Models;

use App\Casts\DateOnly;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 会社の休みの決まりとちがう日（R-13・D-042・D-043）。
 * 日曜日は、はじめから会社の休み。決まりとちがう日だけを、ここに残す
 * （月〜土曜日を休みにした日は is_holiday = true、日曜日を出勤日にした日は false）
 */
#[Fillable(['date', 'is_holiday'])]
class CompanyHoliday extends Model
{
    /**
     * 何も登録していない日が、会社の休みかどうか。日曜日は休み（D-043）
     */
    public static function isHolidayByDefault(CarbonInterface $date): bool
    {
        return $date->isSunday();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'is_holiday' => 'boolean',
        ];
    }
}
