<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-03 の編集：小さな画面で、指示の内容を直す（D-027・D-028）
 */
class OrderUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $changes
     */
    private function update(ProductionOrder $order, array $changes = [])
    {
        return $this->from('/')->patch("/orders/{$order->id}", [
            'product_id' => $order->product_id,
            'quantity' => $order->quantity,
            'material_ready_date' => $order->material_ready_date?->toDateString(),
            'due_date' => $order->due_date->toDateString(),
            'version' => $order->updated_at->toIso8601String(),
            ...$changes,
        ]);
    }

    /**
     * ログインしていなくても、だれでも直せる
     */
    public function test_anyone_can_update_product_quantity_and_dates(): void
    {
        $order = ProductionOrder::factory()->create();
        $other = Product::factory()->create();
        $this->assertGuest();

        $this->update($order, [
            'product_id' => $other->id,
            'quantity' => 250,
            'material_ready_date' => '2026-11-25',
            'due_date' => '2026-11-30',
        ])->assertRedirect('/')->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame($other->id, $order->product_id);
        $this->assertSame(250, $order->quantity);
        $this->assertSame('2026-11-25', $order->material_ready_date->toDateString());
        $this->assertSame('2026-11-30', $order->due_date->toDateString());
    }

    public function test_material_ready_date_can_be_cleared(): void
    {
        $order = ProductionOrder::factory()->create(['material_ready_date' => '2026-11-18']);

        $this->update($order, ['material_ready_date' => null])->assertSessionHasNoErrors();

        $this->assertNull($order->refresh()->material_ready_date);
    }

    /**
     * 内容を直しても、状態・始めた日・完了した日・担当は変わらない
     */
    public function test_updating_details_does_not_touch_status_dates_or_assignees(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::InProgress)->create([
            'started_at' => '2026-11-18 08:00:00',
        ]);
        $order->assignees()->attach(User::factory()->create());

        $this->update($order, ['quantity' => 99])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame('2026-11-18 08:00:00', $order->started_at->toDateTimeString());
        $this->assertNull($order->completed_at);
        $this->assertSame(1, $order->assignees()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            '製品がない' => [['product_id' => null], 'product_id'],
            '製品が見つからない' => [['product_id' => 999999], 'product_id'],
            '数量がない' => [['quantity' => null], 'quantity'],
            '数量が0' => [['quantity' => 0], 'quantity'],
            '数量が数字でない' => [['quantity' => 'たくさん'], 'quantity'],
            '納期がない' => [['due_date' => null], 'due_date'],
            '納期の形がちがう' => [['due_date' => '11/30'], 'due_date'],
            '材料集結日の形がちがう' => [['material_ready_date' => '2026/11/25'], 'material_ready_date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_and_nothing_changes(array $changes, string $field): void
    {
        $order = ProductionOrder::factory()->create(['quantity' => 10, 'due_date' => '2026-11-22']);

        $this->update($order, $changes)->assertSessionHasErrors($field);

        $order->refresh();
        $this->assertSame(10, $order->quantity);
        $this->assertSame('2026-11-22', $order->due_date->toDateString());
    }

    /**
     * 2台の端末で同じ指示を開いて直したとき、あとから保存した人は、先の人の直しを上書きしない
     */
    public function test_saving_with_an_old_version_is_rejected_and_keeps_the_other_change(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));
        $order = ProductionOrder::factory()->create(['quantity' => 10]);
        $openedVersion = $order->updated_at->toIso8601String();

        // 先の人が直す
        $this->travelTo(Carbon::parse('2026-11-20 10:01:00'));
        $this->update($order, ['quantity' => 20])->assertSessionHasNoErrors();

        // あとの人は、開いたときの古い印のまま保存する
        $this->travelTo(Carbon::parse('2026-11-20 10:02:00'));
        $this->update($order, ['quantity' => 30, 'version' => $openedVersion])->assertSessionHasErrors([
            'version' => 'この指示は、ほかの端末で先に直されていました。新しい内容を見てから、もう一度直してください。',
        ]);

        $this->assertSame(20, $order->refresh()->quantity);
    }

    public function test_missing_version_is_rejected(): void
    {
        $order = ProductionOrder::factory()->create(['quantity' => 10]);

        $this->update($order, ['quantity' => 30, 'version' => null])->assertSessionHasErrors('version');

        $this->assertSame(10, $order->refresh()->quantity);
    }

    public function test_unknown_order_returns_not_found(): void
    {
        $this->patch('/orders/999999', [])->assertNotFound();
    }
}
