<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Product;
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

    public function test_board_shows_not_started_and_in_progress_columns_only(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board')
                ->has('columns', 2)
                ->where('columns.0.status', 'not_started')
                ->where('columns.0.label', '未着手')
                ->where('columns.1.status', 'in_progress')
                ->where('columns.1.label', '作業中')
            );
    }

    public function test_orders_are_placed_in_the_column_of_their_status(): void
    {
        ProductionOrder::factory()->count(2)->status(OrderStatus::NotStarted)->create();
        ProductionOrder::factory()->count(3)->status(OrderStatus::InProgress)->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('columns.0.orders', 2)
            ->has('columns.1.orders', 3)
            ->where('columns.0.orders.0.status', 'not_started')
            ->where('columns.1.orders.0.status', 'in_progress')
        );
    }

    /**
     * 完了した指示はボードに出さない（D-016）
     */
    public function test_done_orders_are_not_shown_on_the_board(): void
    {
        ProductionOrder::factory()->status(OrderStatus::NotStarted)->create();
        ProductionOrder::factory()->count(3)->status(OrderStatus::Done)->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('columns', 2)
            ->has('columns.0.orders', 1)
            ->has('columns.1.orders', 0)
        );
    }

    public function test_each_order_shows_product_quantity_dates_assignees_and_status(): void
    {
        $product = Product::factory()->create(['code' => 'SH-0660', 'name' => 'シャフト']);
        $order = ProductionOrder::factory()->status(OrderStatus::InProgress)->create([
            'product_id' => $product->id,
            'quantity' => 120,
            'material_ready_date' => '2026-11-18',
            'due_date' => '2026-11-22',
        ]);
        $order->assignees()->attach(User::factory()->create(['name' => '山田 太郎']));

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('columns.1.orders.0', fn (Assert $card) => $card
                ->where('id', $order->id)
                ->where('productCode', 'SH-0660')
                ->where('productName', 'シャフト')
                ->where('quantity', 120)
                ->where('materialReadyDate', '2026-11-18')
                ->where('dueDate', '2026-11-22')
                ->where('assignees', ['山田 太郎'])
                ->where('status', 'in_progress')
                ->where('isDummy', false)
            )
        );
    }

    /**
     * 担当は何人でも入れられ、足した順に並ぶ（D-018）
     */
    public function test_order_shows_all_assignees_in_the_order_they_were_added(): void
    {
        $order = ProductionOrder::factory()->create();
        $first = User::factory()->create(['name' => '渡辺 一郎']);
        $second = User::factory()->create(['name' => '青木 花子']);
        $third = User::factory()->create(['name' => '佐藤 次郎']);
        $order->assignees()->attach($first);
        $order->assignees()->attach($second);
        $order->assignees()->attach($third);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.assignees', ['渡辺 一郎', '青木 花子', '佐藤 次郎'])
        );
    }

    public function test_order_without_assignees_product_code_or_material_date_is_shown_as_empty(): void
    {
        ProductionOrder::factory()->create([
            'product_id' => Product::factory()->withoutCode(),
            'material_ready_date' => null,
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.assignees', [])
            ->where('columns.0.orders.0.productCode', null)
            ->where('columns.0.orders.0.materialReadyDate', null)
        );
    }

    public function test_orders_are_sorted_by_nearest_due_date(): void
    {
        $make = fn (string $name, string $due) => ProductionOrder::factory()->create([
            'product_id' => Product::factory()->create(['name' => $name]),
            'due_date' => $due,
        ]);
        $make('遅い', '2026-12-10');
        $make('早い', '2026-11-21');
        $make('中間', '2026-11-30');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.productName', '早い')
            ->where('columns.0.orders.1.productName', '中間')
            ->where('columns.0.orders.2.productName', '遅い')
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
     * 指示が増えても、データベースへの問い合わせの回数が増えないこと（製品や担当を1件ずつ読まない）
     */
    public function test_query_count_does_not_grow_with_number_of_orders(): void
    {
        $workers = User::factory()->count(5)->create();
        $makeOrders = function (int $count) use ($workers) {
            ProductionOrder::factory()->count($count)
                ->sequence(['status' => OrderStatus::NotStarted], ['status' => OrderStatus::InProgress])
                ->create()
                ->each(fn (ProductionOrder $order) => $order->assignees()->attach($workers->random(2)->pluck('id')));
        };

        $makeOrders(4);
        $few = $this->countQueries(fn () => $this->get('/')->assertOk());

        $makeOrders(60);
        $many = $this->countQueries(fn () => $this->get('/')->assertOk());

        $this->assertSame($few, $many);
    }

    public function test_history_page_is_reachable_from_the_board(): void
    {
        $this->get('/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('History'));
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
