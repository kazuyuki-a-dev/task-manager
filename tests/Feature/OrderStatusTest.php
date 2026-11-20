<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * R-04 状態を変える（D-017・D-023・D-024）
 */
class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function changeStatus(ProductionOrder $order, string $status)
    {
        return $this->from('/')->patch("/orders/{$order->id}/status", ['status' => $status]);
    }

    public function test_starting_work_records_the_start_time(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 09:15:00'));
        $order = ProductionOrder::factory()->status(OrderStatus::NotStarted)->create();

        $this->changeStatus($order, 'in_progress')->assertRedirect('/')->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame('2026-11-20 09:15:00', $order->started_at->toDateTimeString());
        $this->assertNull($order->completed_at);
    }

    public function test_completing_work_records_the_completion_time_and_keeps_the_start_time(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::InProgress)->create([
            'started_at' => '2026-11-18 08:00:00',
        ]);
        $this->travelTo(Carbon::parse('2026-11-20 16:30:00'));

        $this->changeStatus($order, 'done')->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Done, $order->status);
        $this->assertSame('2026-11-18 08:00:00', $order->started_at->toDateTimeString());
        $this->assertSame('2026-11-20 16:30:00', $order->completed_at->toDateTimeString());
    }

    /**
     * 完了から作業中に戻すと、完了した日は消え、始めた日はそのまま（D-024）
     */
    public function test_reopening_a_done_order_clears_the_completion_time_and_keeps_the_start_time(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::Done)->create([
            'started_at' => '2026-11-18 08:00:00',
            'completed_at' => '2026-11-19 17:00:00',
        ]);
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));

        $this->changeStatus($order, 'in_progress')->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame('2026-11-18 08:00:00', $order->started_at->toDateTimeString());
        $this->assertNull($order->completed_at);
    }

    public function test_completing_again_after_reopening_records_the_new_completion_time(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::Done)->create([
            'completed_at' => '2026-11-19 17:00:00',
        ]);

        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));
        $this->changeStatus($order, 'in_progress');
        $this->travelTo(Carbon::parse('2026-11-21 15:00:00'));
        $this->changeStatus($order, 'done');

        $this->assertSame('2026-11-21 15:00:00', $order->refresh()->completed_at->toDateTimeString());
    }

    /**
     * 担当に入っていない指示も、だれでも変えられる（D-023）
     */
    public function test_anyone_can_change_an_order_without_assignees_or_login(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::NotStarted)->create();
        $this->assertCount(0, $order->assignees);
        $this->assertGuest();

        $this->changeStatus($order, 'in_progress')->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::InProgress, $order->refresh()->status);
    }

    public function test_skipping_a_step_is_rejected(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::NotStarted)->create();

        $this->changeStatus($order, 'done')->assertRedirect('/')->assertSessionHasErrors('status');

        $order->refresh();
        $this->assertSame(OrderStatus::NotStarted, $order->status);
        $this->assertNull($order->completed_at);
    }

    /**
     * 2台の端末で同じボタンを続けて押したとき、あとの人には「先に変わっていた」と出し、何も変えない
     */
    public function test_pressing_the_same_button_twice_is_rejected_with_a_message_and_changes_nothing(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::InProgress)->create();
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));
        $this->changeStatus($order, 'done')->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-11-20 10:00:05'));
        $this->changeStatus($order, 'done')->assertSessionHasErrors([
            'status' => 'この指示はもう「完了」になっています。ほかの端末で先に変わっていました。',
        ]);

        $this->assertSame('2026-11-20 10:00:00', $order->refresh()->completed_at->toDateTimeString());
    }

    public function test_unknown_status_is_rejected(): void
    {
        $order = ProductionOrder::factory()->create();

        $this->changeStatus($order, 'cancelled')->assertSessionHasErrors('status');
        $this->from('/')->patch("/orders/{$order->id}/status", [])->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::NotStarted, $order->refresh()->status);
    }

    public function test_unknown_order_returns_not_found(): void
    {
        $this->patch('/orders/999999/status', ['status' => 'in_progress'])->assertNotFound();
    }
}
