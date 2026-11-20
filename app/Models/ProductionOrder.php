<?php

namespace App\Models;

use App\Enums\OrderLogAction;
use App\Enums\OrderStatus;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * 作業を始められない理由（D-030）。始められるときは空。材料集結日は空でも始められる
     *
     * @return list<string>
     */
    public function startBlockers(): array
    {
        $reasons = [];
        if ($this->assignees->isEmpty()) {
            $reasons[] = '担当を足すと始められます';
        }

        return $reasons;
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

        $previous = $this->status;
        $this->status = $next;
        $this->save();

        $this->log(OrderLogAction::Status, $previous->label(), $next->label());
    }

    /**
     * 変更の記録を1つ残す（R-08・D-041）。だれが変えたかは残さない（D-023）
     */
    public function log(OrderLogAction $action, ?string $old = null, ?string $new = null): void
    {
        $this->logs()->create([
            'action' => $action,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }

    /**
     * 記録に残す製品の名前。あとで製品や会社の名前を直しても、記録は当時のまま
     */
    public static function productLabel(Product $product): string
    {
        return $product->company->name.'・'.($product->code !== null ? $product->code.' ' : '').$product->name;
    }

    /**
     * 変更の記録。新しい順
     *
     * @return HasMany<OrderLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(OrderLog::class)->orderByDesc('id');
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
