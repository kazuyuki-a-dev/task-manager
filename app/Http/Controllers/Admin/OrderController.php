<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderLogAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 指示の登録と削除（R-03）。管理者だけが使う（D-004・D-027）
 */
class OrderController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/OrderCreate', [
            // 取引会社を選ぶと、その会社の製品だけが選べる
            'companies' => Company::query()
                ->with(['products' => fn ($q) => $q->orderByRaw('code IS NULL')->orderBy('code')->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Company $company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'products' => $company->products->map(fn (Product $product) => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'name' => $product->name,
                    ])->all(),
                ])
                ->all(),
            // 担当に足せる社員。使えなくした人は出さない
            'members' => User::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
            'material_ready_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'assignee_ids' => ['array'],
            // 担当は使える社員だけ。同じ人を二重に入れない
            'assignee_ids.*' => ['integer', 'distinct', 'exists:users,id,is_active,1'],
        ], [
            'product_id.required' => '製品を選んでください。',
            'product_id.exists' => '選んだ製品が見つかりません。',
            'quantity.required' => '数量を入れてください。',
            'quantity.integer' => '数量は数字で入れてください。',
            'quantity.min' => '数量は1以上にしてください。',
            'quantity.max' => '数量が大きすぎます。',
            'material_ready_date.date_format' => '材料集結日の形がちがいます。',
            'due_date.required' => '納期を入れてください。',
            'due_date.date_format' => '納期の形がちがいます。',
            'assignee_ids.*.distinct' => '同じ人が二重に入っています。',
            'assignee_ids.*.exists' => '担当に入れられない社員がいます。',
        ]);

        // 指示と担当はまとめて登録する。途中で失敗したら、どちらも登録しない
        DB::transaction(function () use ($validated) {
            $order = ProductionOrder::create([
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
                'material_ready_date' => $validated['material_ready_date'] ?? null,
                'due_date' => $validated['due_date'],
                // 登録した指示は未着手から（ボードの「未着手」に出る）
                'status' => OrderStatus::NotStarted,
            ]);
            $order->assignees()->attach($validated['assignee_ids'] ?? []);

            // 変更の記録（R-08）：登録したことと、はじめから入れた担当
            $order->log(OrderLogAction::Created);
            foreach ($order->assignees()->get(['users.id', 'users.name']) as $assignee) {
                $order->log(OrderLogAction::AssigneeAdded, null, $assignee->name);
            }
        });

        // 続けて何件も入れられるように、同じ画面に戻る
        return back()->with('notice', '指示を登録しました。ボードの「未着手」に出ています。');
    }

    /**
     * 指示を消す。ボードの小さな画面から、管理者だけが使う
     */
    public function destroy(ProductionOrder $order): RedirectResponse
    {
        // 担当のつながりは、データベースの設定でいっしょに消える
        $order->delete();

        return back();
    }
}
