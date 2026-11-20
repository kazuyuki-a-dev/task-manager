<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\BoardController;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * R-01 進み具合のボード
 */
class BoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_shows_three_columns_in_status_order(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board')
                ->has('columns', 3)
                ->where('columns.0.status', 'not_started')
                ->where('columns.0.label', '未着手')
                ->where('columns.1.status', 'in_progress')
                ->where('columns.1.label', '作業中')
                ->where('columns.2.status', 'done')
                ->where('columns.2.label', '完了')
            );
    }

    public function test_orders_are_placed_in_the_column_of_their_status(): void
    {
        ProductionOrder::factory()->count(2)->status(OrderStatus::NotStarted)->create();
        ProductionOrder::factory()->count(3)->status(OrderStatus::InProgress)->create();
        ProductionOrder::factory()->count(1)->status(OrderStatus::Done)->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('columns.0.orders', 2)
            ->has('columns.1.orders', 3)
            ->has('columns.2.orders', 1)
            ->where('columns.0.orders.0.status', 'not_started')
            ->where('columns.1.orders.0.status', 'in_progress')
            ->where('columns.2.orders.0.status', 'done')
        );
    }

    public function test_each_order_shows_product_quantity_dates_assignee_and_status(): void
    {
        $worker = User::factory()->create(['name' => '山田 太郎']);
        ProductionOrder::factory()->create([
            'product_name' => 'シャフト-001',
            'quantity' => 120,
            'material_ready_date' => '2026-11-18',
            'due_date' => '2026-11-22',
            'assignee_id' => $worker->id,
            'status' => OrderStatus::InProgress,
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('columns.1.orders.0', fn (Assert $order) => $order
                ->has('id')
                ->where('productName', 'シャフト-001')
                ->where('quantity', 120)
                ->where('materialReadyDate', '2026-11-18')
                ->where('dueDate', '2026-11-22')
                ->where('assignee', '山田 太郎')
                ->where('status', 'in_progress')
                ->where('isDummy', false)
            )
        );
    }

    public function test_order_without_assignee_or_material_date_is_shown_as_empty(): void
    {
        ProductionOrder::factory()->create([
            'assignee_id' => null,
            'material_ready_date' => null,
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.assignee', null)
            ->where('columns.0.orders.0.materialReadyDate', null)
        );
    }

    public function test_active_orders_are_sorted_by_nearest_due_date(): void
    {
        ProductionOrder::factory()->create(['product_name' => '遅い', 'due_date' => '2026-12-10']);
        ProductionOrder::factory()->create(['product_name' => '早い', 'due_date' => '2026-11-21']);
        ProductionOrder::factory()->create(['product_name' => '中間', 'due_date' => '2026-11-30']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.productName', '早い')
            ->where('columns.0.orders.1.productName', '中間')
            ->where('columns.0.orders.2.productName', '遅い')
        );
    }

    public function test_done_column_shows_only_the_newest_up_to_the_limit_with_total_count(): void
    {
        $limit = BoardController::DONE_LIMIT;

        ProductionOrder::factory()->count($limit)->status(OrderStatus::Done)
            ->create(['updated_at' => now()->subDays(3)]);
        $newest = ProductionOrder::factory()->status(OrderStatus::Done)
            ->create(['updated_at' => now()]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('columns.2.orders', $limit)
            ->where('columns.2.orders.0.id', $newest->id)
            ->where('doneTotal', $limit + 1)
            ->where('doneLimit', $limit)
        );
    }

    public function test_dummy_orders_are_marked(): void
    {
        ProductionOrder::factory()->dummy()->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.isDummy', true)
        );
    }

    /**
     * 指示が増えても、データベースへの問い合わせの回数が増えないこと（担当の名前を1件ずつ読まない）
     */
    public function test_query_count_does_not_grow_with_number_of_orders(): void
    {
        $workers = User::factory()->count(5)->create();
        // 状態はくじ引きにせず、順番に割り当てる。
        // どこかの列が0件だと、担当の名前を読む問い合わせが省かれて回数が変わってしまうため
        $makeOrders = fn (int $count) => ProductionOrder::factory()->count($count)
            ->sequence(...array_map(fn (OrderStatus $status) => ['status' => $status], OrderStatus::cases()))
            ->state(fn () => ['assignee_id' => $workers->random()->id])
            ->create();

        $makeOrders(3);
        $few = $this->countQueries(fn () => $this->get('/')->assertOk());

        $makeOrders(60);
        $many = $this->countQueries(fn () => $this->get('/')->assertOk());

        $this->assertSame($few, $many);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
