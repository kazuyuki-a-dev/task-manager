<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\OrderLog;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * R-08 変更の記録（D-023・D-041）
 */
class OrderLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{action: string, old: ?string, new: ?string}>
     */
    private function logsOf(ProductionOrder $order): array
    {
        return OrderLog::where('production_order_id', $order->id)->orderBy('id')->get()
            ->map(fn (OrderLog $log) => ['action' => $log->action->value, 'old' => $log->old_value, 'new' => $log->new_value])
            ->all();
    }

    public function test_status_changes_are_logged_with_from_and_to(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::NotStarted)->withAssignee()->create();

        $this->from('/')->patch("/orders/{$order->id}/status", ['status' => 'in_progress']);
        $this->from('/')->patch("/orders/{$order->id}/status", ['status' => 'done']);
        $this->from('/')->patch("/orders/{$order->id}/status", ['status' => 'in_progress']);

        $this->assertSame([
            ['action' => 'status', 'old' => '未着手', 'new' => '作業中'],
            ['action' => 'status', 'old' => '作業中', 'new' => '完了'],
            ['action' => 'status', 'old' => '完了', 'new' => '作業中'],
        ], $this->logsOf($order));
    }

    /**
     * 変えられなかったとき（順番をとばす、ほかの端末で先に変わっていた）は、記録しない
     */
    public function test_rejected_status_changes_are_not_logged(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::NotStarted)->withAssignee()->create();

        $this->from('/')->patch("/orders/{$order->id}/status", ['status' => 'done'])->assertSessionHasErrors('status');

        $this->assertSame([], $this->logsOf($order));
    }

    /**
     * 内容を直したときは、変わった項目だけを1つずつ残す
     */
    public function test_only_changed_details_are_logged_one_by_one(): void
    {
        $order = ProductionOrder::factory()->create([
            'quantity' => 10,
            'material_ready_date' => '2026-11-18',
            'due_date' => '2026-11-22',
        ]);

        $this->from('/')->patch("/orders/{$order->id}", [
            'product_id' => $order->product_id,
            'quantity' => 25,
            'material_ready_date' => null,
            'due_date' => '2026-11-22',
            'version' => $order->updated_at->toIso8601String(),
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            ['action' => 'quantity', 'old' => '10', 'new' => '25'],
            ['action' => 'material_ready_date', 'old' => '2026-11-18', 'new' => null],
        ], $this->logsOf($order));
    }

    /**
     * 製品を変えたときは、会社名・品番・品名を、変えたときのまま残す。あとで名前を直しても記録は変わらない
     */
    public function test_product_change_keeps_the_names_at_that_time(): void
    {
        $company = Company::factory()->create(['name' => 'ダミー東和工業']);
        $from = Product::factory()->create(['company_id' => $company->id, 'code' => 'SH-0660', 'name' => 'シャフト']);
        $to = Product::factory()->create(['company_id' => $company->id, 'code' => null, 'name' => 'ステー']);
        $order = ProductionOrder::factory()->create(['product_id' => $from->id]);

        $this->from('/')->patch("/orders/{$order->id}", [
            'product_id' => $to->id,
            'quantity' => $order->quantity,
            'material_ready_date' => $order->material_ready_date?->toDateString(),
            'due_date' => $order->due_date->toDateString(),
            'version' => $order->updated_at->toIso8601String(),
        ])->assertSessionHasNoErrors();
        $to->update(['name' => 'ステー（新しい名前）']);

        $this->assertSame([
            ['action' => 'product', 'old' => 'ダミー東和工業・SH-0660 シャフト', 'new' => 'ダミー東和工業・ステー'],
        ], $this->logsOf($order));
    }

    public function test_saving_without_changes_logs_nothing(): void
    {
        $order = ProductionOrder::factory()->create();

        $this->from('/')->patch("/orders/{$order->id}", [
            'product_id' => $order->product_id,
            'quantity' => $order->quantity,
            'material_ready_date' => $order->material_ready_date?->toDateString(),
            'due_date' => $order->due_date->toDateString(),
            'version' => $order->updated_at->toIso8601String(),
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $this->logsOf($order));
    }

    /**
     * 担当を足した・外したも残す。名前は、そのときのまま
     */
    public function test_assignee_changes_are_logged_with_names_at_that_time(): void
    {
        $order = ProductionOrder::factory()->create();
        $worker = User::factory()->create(['name' => '山田 太郎']);

        $this->from('/')->post("/orders/{$order->id}/assignees", ['user_id' => $worker->id])->assertSessionHasNoErrors();
        $this->from('/')->delete("/orders/{$order->id}/assignees/{$worker->id}")->assertSessionHasNoErrors();
        $worker->update(['name' => '山田 一郎']);

        $this->assertSame([
            ['action' => 'assignee_added', 'old' => null, 'new' => '山田 太郎'],
            ['action' => 'assignee_removed', 'old' => '山田 太郎', 'new' => null],
        ], $this->logsOf($order));
    }

    /**
     * 管理者が登録したときは、「登録した」と、はじめから入れた担当を残す
     */
    public function test_creating_an_order_logs_creation_and_initial_assignees(): void
    {
        $this->actingAs(User::factory()->admin()->create())->withSession(['admin_last_activity' => now()->getTimestamp()]);
        $worker = User::factory()->create(['name' => '佐藤 花子']);

        $this->from('/admin/orders/create')->post('/admin/orders', [
            'product_id' => Product::factory()->create()->id,
            'quantity' => 10,
            'material_ready_date' => null,
            'due_date' => '2026-11-30',
            'assignee_ids' => [$worker->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            ['action' => 'created', 'old' => null, 'new' => null],
            ['action' => 'assignee_added', 'old' => null, 'new' => '佐藤 花子'],
        ], $this->logsOf(ProductionOrder::sole()));
    }

    /**
     * ボードの小さな画面には、指示ごとの記録が新しい順に出る。いつ変えたかも出る。だれが変えたかは出さない
     */
    public function test_board_shows_each_orders_logs_newest_first(): void
    {
        $order = ProductionOrder::factory()->status(OrderStatus::NotStarted)->withAssignee()->create();
        $other = ProductionOrder::factory()->status(OrderStatus::NotStarted)->withAssignee()->create();

        $this->travelTo(Carbon::parse('2026-11-20 09:15:00'));
        $this->from('/')->patch("/orders/{$order->id}/status", ['status' => 'in_progress']);
        $this->travelTo(Carbon::parse('2026-11-20 10:30:00'));
        $this->from('/')->patch("/orders/{$order->id}/status", ['status' => 'not_started']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders', function ($orders) use ($order, $other) {
                $mine = collect($orders)->firstWhere('id', $order->id)['logs'];
                $theirs = collect($orders)->firstWhere('id', $other->id)['logs'];

                return count($mine) === 2
                    && $mine[0]['at'] === '2026-11-20 10:30'
                    && $mine[0]['label'] === '状態'
                    && $mine[0]['oldValue'] === '作業中'
                    && $mine[0]['newValue'] === '未着手'
                    && $mine[1]['at'] === '2026-11-20 09:15'
                    // だれが変えたかは残さない（D-023）
                    && ! array_key_exists('user', $mine[0])
                    && $theirs === [];
            }));
    }

    /**
     * 指示を消すと、その指示の記録もいっしょに消える
     */
    public function test_deleting_an_order_deletes_its_logs(): void
    {
        $order = ProductionOrder::factory()->withAssignee()->create();
        $this->from('/')->patch("/orders/{$order->id}/status", ['status' => 'in_progress']);
        $this->assertSame(1, OrderLog::count());

        $this->actingAs(User::factory()->admin()->create())->withSession(['admin_last_activity' => now()->getTimestamp()])
            ->from('/')->delete("/admin/orders/{$order->id}")->assertRedirect('/');

        $this->assertSame(0, OrderLog::count());
    }
}
