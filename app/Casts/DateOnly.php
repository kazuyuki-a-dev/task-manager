<?php

namespace App\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 日付だけの列（納期・休みの日など）。読むときは Carbon（その日の 0:00）、保存するときは「2026-11-20」の形にする。
 * Laravel の 'date' だと、SQLite では「2026-11-20 00:00:00」の形で保存されて、日付の文字と比べたときに合わなくなるため（D-055）。
 * MySQL の date の列では、どちらでも同じ値になる
 *
 * @implements CastsAttributes<Carbon, mixed>
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof CarbonInterface ? $value : Carbon::parse($value))->toDateString();
    }
}
