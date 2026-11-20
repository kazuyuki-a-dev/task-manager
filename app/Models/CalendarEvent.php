<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 会社カレンダーの行事（R-13・D-040）。会社の休みも、今はここに登録する
 */
#[Fillable(['date', 'title', 'body'])]
class CalendarEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
