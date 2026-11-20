<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 製造指示。指示1件＝タスク1件（D-002）
 */
#[Fillable(['product_name', 'quantity', 'material_ready_date', 'due_date', 'assignee_id', 'status', 'is_dummy'])]
class ProductionOrder extends Model
{
    /** @use HasFactory<ProductionOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'material_ready_date' => 'date',
            'due_date' => 'date',
            'status' => OrderStatus::class,
            'is_dummy' => 'boolean',
        ];
    }

    /**
     * 担当の社員
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
