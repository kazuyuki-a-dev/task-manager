<?php

namespace App\Models;

use App\Enums\OrderLogAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 指示の変更の記録（R-08・D-041）。いつ・何を（何から何に）変えたか。だれが変えたかは残さない
 */
#[Fillable(['production_order_id', 'action', 'old_value', 'new_value'])]
class OrderLog extends Model
{
    // 記録はあとから直さないので、作った日時だけを持つ
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => OrderLogAction::class,
            'created_at' => 'datetime',
        ];
    }
}
