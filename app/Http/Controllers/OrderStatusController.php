<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 指示の状態を変える（R-04）。だれでも、どの指示も変えられる（D-023）
 */
class OrderStatusController extends Controller
{
    public function update(Request $request, ProductionOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);
        $next = OrderStatus::from($validated['status']);

        // 2台の端末でほぼ同時に押されたときに、あとの人が前の人の変更を上書きしないよう、
        // 読みなおして鍵をかけてから確かめる
        DB::transaction(function () use ($order, $next) {
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);

            if (! $order->status->canChangeTo($next)) {
                throw ValidationException::withMessages([
                    'status' => "この指示はもう「{$order->status->label()}」になっています。ほかの端末で先に変わっていました。",
                ]);
            }

            $order->changeStatus($next);
        });

        return back();
    }
}
