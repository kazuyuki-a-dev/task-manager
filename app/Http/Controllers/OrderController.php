<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 指示の内容を直す（R-03 の編集）。だれでもできる（D-027）
 */
class OrderController extends Controller
{
    public function update(Request $request, ProductionOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
            'material_ready_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            // 画面を開いたときの「最後に直された日時」。ほかの端末の直しを上書きしないために使う
            'version' => ['required', 'string'],
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
        ]);

        DB::transaction(function () use ($order, $validated) {
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);

            if ($order->updated_at->toIso8601String() !== $validated['version']) {
                throw ValidationException::withMessages([
                    'version' => 'この指示は、ほかの端末で先に直されていました。新しい内容を見てから、もう一度直してください。',
                ]);
            }

            $order->update([
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
                'material_ready_date' => $validated['material_ready_date'] ?? null,
                'due_date' => $validated['due_date'],
            ]);
        });

        return back();
    }
}
