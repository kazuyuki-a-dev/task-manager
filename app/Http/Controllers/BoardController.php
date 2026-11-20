<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 進み具合のボード（R-01）
 */
class BoardController extends Controller
{
    /**
     * 完了の列に出す件数の上限。
     * 完了した指示は毎日増え続けるので、全部は出さずに新しいものだけ出す。
     */
    public const DONE_LIMIT = 50;

    public function __invoke(): Response
    {
        // 未着手と作業中は、納期の近い順
        $active = ProductionOrder::query()
            ->with('assignee:id,name')
            ->whereIn('status', [OrderStatus::NotStarted, OrderStatus::InProgress])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        // 完了は、最後に変えた順（新しいものが上）
        $done = ProductionOrder::query()
            ->with('assignee:id,name')
            ->where('status', OrderStatus::Done)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::DONE_LIMIT)
            ->get();

        $columns = [
            $this->column(OrderStatus::NotStarted, $active->where('status', OrderStatus::NotStarted)),
            $this->column(OrderStatus::InProgress, $active->where('status', OrderStatus::InProgress)),
            $this->column(OrderStatus::Done, $done),
        ];

        return Inertia::render('Board', [
            'columns' => $columns,
            // 完了は上限までしか出さないので、全部で何件あるかを別に渡す
            'doneTotal' => ProductionOrder::where('status', OrderStatus::Done)->count(),
            'doneLimit' => self::DONE_LIMIT,
        ]);
    }

    /**
     * @param  Collection<int, ProductionOrder>  $orders
     * @return array<string, mixed>
     */
    private function column(OrderStatus $status, Collection $orders): array
    {
        return [
            'status' => $status->value,
            'label' => $status->label(),
            'orders' => $orders->values()->map(fn (ProductionOrder $order) => [
                'id' => $order->id,
                'productName' => $order->product_name,
                'quantity' => $order->quantity,
                'materialReadyDate' => $order->material_ready_date?->toDateString(),
                'dueDate' => $order->due_date->toDateString(),
                'assignee' => $order->assignee?->name,
                'status' => $order->status->value,
                'isDummy' => $order->is_dummy,
            ])->all(),
        ];
    }
}
