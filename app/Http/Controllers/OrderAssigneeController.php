<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 指示の担当を足す・外す（R-11）。だれでもできる（D-018・D-026）
 */
class OrderAssigneeController extends Controller
{
    public function store(Request $request, ProductionOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                // 使えなくした社員は足せない（R-07）
                Rule::exists('users', 'id')->where('is_active', true),
                // すでに担当に入っている人は足せない
                Rule::unique('order_assignees', 'user_id')->where('production_order_id', $order->id),
            ],
        ], [
            'user_id.exists' => '選んだ社員は、今は担当に足せません。',
            'user_id.unique' => 'その社員は、もう担当に入っています。',
        ]);

        $order->assignees()->attach($validated['user_id']);

        return back();
    }

    public function destroy(ProductionOrder $order, User $user): RedirectResponse
    {
        // 使えなくした社員でも、担当からは外せる
        if ($order->assignees()->detach($user->id) === 0) {
            throw ValidationException::withMessages([
                'user_id' => "{$user->name}さんは、もう担当から外れていました。ほかの端末で先に変わっていました。",
            ]);
        }

        return back();
    }
}
