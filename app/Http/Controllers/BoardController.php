<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 進み具合のボード（R-01）。
 * 完了した指示は出さない。完了は履歴の画面（R-10）で見る（D-016）
 */
class BoardController extends Controller
{
    /**
     * ボードに出す状態と、その並び
     */
    private const STATUSES = [OrderStatus::NotStarted, OrderStatus::InProgress];

    public function __invoke(): Response
    {
        // 納期の近い順。製品と担当はまとめて読む（指示が増えても問い合わせの回数を増やさない）
        $orders = ProductionOrder::query()
            ->with(['product:id,code,name', 'assignees:id,name'])
            ->whereIn('status', self::STATUSES)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $columns = array_map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'orders' => $this->toCards($orders->where('status', $status)),
        ], self::STATUSES);

        return Inertia::render('Board', [
            'columns' => $columns,
        ]);
    }

    /**
     * @param  Collection<int, ProductionOrder>  $orders
     * @return list<array<string, mixed>>
     */
    private function toCards(Collection $orders): array
    {
        return $orders->values()->map(fn (ProductionOrder $order) => [
            'id' => $order->id,
            'productCode' => $order->product->code,
            'productName' => $order->product->name,
            'quantity' => $order->quantity,
            'materialReadyDate' => $order->material_ready_date?->toDateString(),
            'dueDate' => $order->due_date->toDateString(),
            'assignees' => $order->assignees->map(fn (User $user) => $user->name)->all(),
            'status' => $order->status->value,
            'isDummy' => $order->is_dummy,
        ])->all();
    }
}
