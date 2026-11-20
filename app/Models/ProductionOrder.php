<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 製造指示。指示1件＝タスク1件（D-002）
 */
#[Fillable(['product_id', 'quantity', 'material_ready_date', 'due_date', 'status', 'started_at', 'completed_at', 'is_dummy'])]
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
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_dummy' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 担当の社員（何人でも。D-018）。足した順に並べる
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_assignees')
            ->withTimestamps()
            ->orderBy('order_assignees.id');
    }
}
