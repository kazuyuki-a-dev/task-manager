<?php

namespace App\Http\Controllers;

use App\Enums\Deadline;
use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 進み具合のボード（R-01）と期限の見える化（R-02）。
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
            ->with(['product:id,company_id,code,name', 'product.company:id,name', 'assignees:id,name'])
            ->whereIn('status', self::STATUSES)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $cards = $this->toCards($orders);

        $columns = array_map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'orders' => array_values(array_filter($cards, fn (array $card) => $card['status'] === $status->value)),
        ], self::STATUSES);

        // 画面のいちばん上にまとめて出す。遅れを先に、そのあと期限間近。どちらも納期の近い順
        // （$cards はもう納期の近い順なので、遅れ・期限間近で分けて並べるだけでよい）
        $alerts = [
            ...array_filter($cards, fn (array $card) => $card['deadline'] === Deadline::Overdue->value),
            ...array_filter($cards, fn (array $card) => $card['deadline'] === Deadline::Soon->value),
        ];

        return Inertia::render('Board', [
            'columns' => $columns,
            'alerts' => array_values($alerts),
            'today' => today()->toDateString(),
            // 担当を足すプルダウンに出す社員。使えなくした人は出さない（R-11）
            'members' => User::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->all(),
            // 小さな画面で製品を選ぶための一覧。品番か品名の一部で、画面の中で探す（R-12）
            'products' => Product::query()
                ->with('company:id,name')
                ->orderByRaw('code IS NULL')
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'companyName' => $product->company->name,
                    'code' => $product->code,
                    'name' => $product->name,
                ])
                ->all(),
        ]);
    }

    /**
     * @param  Collection<int, ProductionOrder>  $orders
     * @return list<array<string, mixed>>
     */
    private function toCards(Collection $orders): array
    {
        $today = today();

        return $orders->values()->map(function (ProductionOrder $order) use ($today) {
            $daysLeft = Deadline::daysLeft($order->due_date, $today);

            return [
                'id' => $order->id,
                'productId' => $order->product_id,
                'companyName' => $order->product->company->name,
                'productCode' => $order->product->code,
                'productName' => $order->product->name,
                'quantity' => $order->quantity,
                'materialReadyDate' => $order->material_ready_date?->toDateString(),
                'dueDate' => $order->due_date->toDateString(),
                'daysLeft' => $daysLeft,
                'deadline' => Deadline::fromDaysLeft($daysLeft)?->value,
                'assignees' => $order->assignees->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->all(),
                'status' => $order->status->value,
                'isDummy' => $order->is_dummy,
                // 作業を始められない理由。未着手のときだけ使う（D-030）
                'startBlockers' => $order->startBlockers(),
                // 小さな画面で直すときに、ほかの端末の直しを上書きしないための印
                'version' => $order->updated_at->toIso8601String(),
            ];
        })->all();
    }
}
