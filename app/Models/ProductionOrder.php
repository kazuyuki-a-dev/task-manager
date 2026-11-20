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
     * 状態を変え、始めた日・完了した日も合わせて直す（D-017・D-024・D-025）。
     * 変えてよいかは、呼ぶ前に OrderStatus::canChangeTo で確かめておく
     */
    public function changeStatus(OrderStatus $next): void
    {
        $now = now();

        if ($next === OrderStatus::NotStarted) {
            // 作業中から戻したときは、始めた日を消す
            $this->started_at = null;
        }

        if ($next === OrderStatus::InProgress) {
            // 完了から戻したときは、始めた日を変えない。未着手からのときは、ここで始めた日が入る
            $this->started_at ??= $now;
            // 完了から戻したときは、完了した日を消す
            $this->completed_at = null;
        }

        if ($next === OrderStatus::Done) {
            $this->completed_at = $now;
        }

        $this->status = $next;
        $this->save();
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
