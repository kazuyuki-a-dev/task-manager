<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 完了の履歴（R-10・D-016・D-037）。だれでも見られる
 */
class HistoryController extends Controller
{
    /**
     * 1ページに出す製品のカードの数
     */
    public const PER_PAGE = 30;

    /**
     * 小さな画面に出す、1つの製品の完了した指示の数の上限（新しいものから）
     */
    public const ORDERS_LIMIT = 100;

    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        // 選んでいる取引会社。選んでいなければ、すべての会社
        $companyId = $request->integer('company') ?: null;
        $productId = $request->integer('product') ?: null;

        return Inertia::render('History', [
            'q' => $q,
            'companyId' => $companyId,
            // 小さな画面を開いたときは、その製品の分だけを取りなおすので、カードは読みなおさない（関数にしておくと、いるときだけ読む）
            'companies' => fn () => $this->companies(),
            'products' => fn () => $this->products($q, $companyId),
            'selected' => fn () => $productId !== null ? $this->selected($productId) : null,
        ]);
    }

    /**
     * 取引会社のプルダウン。完了した指示がある会社だけを、会社名の順に出す。
     * 会社ごとに、完了した指示がある製品の数も出す
     *
     * @return list<array{id: int, name: string, productsCount: int}>
     */
    private function companies(): array
    {
        $productsWithDone = fn (Builder $query) => $query->whereHas('orders', fn (Builder $o) => $o->where('status', OrderStatus::Done));

        return Company::query()
            ->whereHas('products', $productsWithDone)
            ->withCount(['products as products_count' => $productsWithDone])
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'productsCount' => $company->products_count,
            ])
            ->all();
    }

    /**
     * 完了した指示がある製品のカード。最後に完了した日が新しい順
     *
     * @return array<string, mixed>
     */
    private function products(string $q, ?int $companyId): array
    {
        $done = fn (Builder $query) => $query->where('status', OrderStatus::Done);

        $page = Product::query()
            ->with('company:id,name')
            ->whereHas('orders', $done)
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->withCount(['orders as done_count' => $done])
            ->withMax(['orders as last_completed_at' => $done], 'completed_at')
            ->when($q !== '', function (Builder $query) use ($q) {
                // 「%」や「_」を打っても、何にでも合う印として扱われないようにする
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn (Builder $w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $like)));
            })
            ->orderByDesc('last_completed_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'data' => $page->getCollection()->map(fn (Product $product) => [
                'id' => $product->id,
                'companyName' => $product->company->name,
                'code' => $product->code,
                'name' => $product->name,
                'doneCount' => $product->done_count,
                'lastCompletedAt' => Carbon::parse($product->last_completed_at)->format('Y-m-d H:i'),
                'isDummy' => $product->is_dummy,
            ])->all(),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
        ];
    }

    /**
     * 小さな画面に出す、1つの製品の完了した指示。納期の新しい順。
     * たくさんたまるので、1件は「年・納期・作業者」だけにする（D-038）
     *
     * @return array<string, mixed>|null
     */
    private function selected(int $productId): ?array
    {
        $product = Product::with('company:id,name')->find($productId);
        if ($product === null) {
            return null;
        }

        $done = ProductionOrder::query()
            ->where('product_id', $product->id)
            ->where('status', OrderStatus::Done);

        return [
            'id' => $product->id,
            'companyName' => $product->company->name,
            'code' => $product->code,
            'name' => $product->name,
            'total' => (clone $done)->count(),
            'limit' => self::ORDERS_LIMIT,
            'orders' => $done
                ->with('assignees:id,name')
                ->orderByDesc('due_date')
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->limit(self::ORDERS_LIMIT)
                ->get()
                ->map(fn (ProductionOrder $order) => [
                    'id' => $order->id,
                    // 画面では「2026年 納期 11/16(月)」のように、納期の年と日付を出す
                    'dueDate' => $order->due_date->toDateString(),
                    // 消した社員の名前も残す（D-036）
                    'assignees' => $order->assignees->map(fn (User $user) => $user->name)->all(),
                ])
                ->all(),
        ];
    }
}
