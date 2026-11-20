<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\HistoryController;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-10 完了の履歴（D-016・D-017・D-037）
 */
class HistoryTest extends TestCase
{
    use RefreshDatabase;

    private function done(Product $product, string $completedAt, array $attributes = []): ProductionOrder
    {
        return ProductionOrder::factory()->status(OrderStatus::Done)->create([
            'product_id' => $product->id,
            'completed_at' => $completedAt,
            ...$attributes,
        ]);
    }

    /**
     * ログインしていなくても、だれでも見られる
     */
    public function test_anyone_can_see_the_history(): void
    {
        $this->assertGuest();
        $this->get('/history')->assertOk()->assertInertia(fn (Assert $page) => $page->component('History'));
    }

    /**
     * カードに出るのは、完了した指示がある製品だけ。最後に完了した日が新しい順
     */
    public function test_cards_show_products_with_done_orders_newest_first(): void
    {
        $company = Company::factory()->create(['name' => 'ダミー東和工業']);
        $old = Product::factory()->create(['company_id' => $company->id, 'code' => 'AA-0001', 'name' => '前に完了']);
        $new = Product::factory()->create(['company_id' => $company->id, 'code' => null, 'name' => '最近完了']);
        $notDone = Product::factory()->create(['name' => 'まだ作業中']);
        $this->done($old, '2026-11-10 17:00:00');
        $this->done($old, '2026-11-12 16:30:00');
        $this->done($new, '2026-11-19 09:15:00');
        ProductionOrder::factory()->status(OrderStatus::InProgress)->create(['product_id' => $notDone->id]);
        // 完了でない指示は、完了した数に入れない
        ProductionOrder::factory()->status(OrderStatus::NotStarted)->create(['product_id' => $old->id]);

        $this->get('/history')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 2)
            ->where('products.data.0', ['id' => $new->id, 'companyName' => 'ダミー東和工業', 'code' => null, 'name' => '最近完了', 'doneCount' => 1, 'lastCompletedAt' => '2026-11-19 09:15', 'isDummy' => false])
            ->where('products.data.1', ['id' => $old->id, 'companyName' => 'ダミー東和工業', 'code' => 'AA-0001', 'name' => '前に完了', 'doneCount' => 2, 'lastCompletedAt' => '2026-11-12 16:30', 'isDummy' => false])
            ->where('selected', null)
        );
    }

    /**
     * 取引会社・品番・品名の一部で探せる
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function searches(): array
    {
        return [
            '品名の一部' => ['シャ', ['シャフト']],
            '品番の一部（小文字）' => ['fl-', ['フランジ']],
            '取引会社の一部' => ['北陸', ['フランジ']],
            'どれにも合わない' => ['ないもの', []],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('searches')]
    public function test_search_by_company_code_or_name(string $q, array $expected): void
    {
        $towa = Company::factory()->create(['name' => 'ダミー東和工業']);
        $hokuriku = Company::factory()->create(['name' => 'ダミー北陸製作所']);
        $this->done(Product::factory()->create(['company_id' => $towa->id, 'code' => 'SH-0660', 'name' => 'シャフト']), '2026-11-10 10:00:00');
        $this->done(Product::factory()->create(['company_id' => $hokuriku->id, 'code' => 'FL-0100', 'name' => 'フランジ']), '2026-11-11 10:00:00');

        $this->get('/history?q='.urlencode($q))->assertInertia(fn (Assert $page) => $page
            ->where('q', $q)
            ->where('products.data', fn ($data) => collect($data)->pluck('name')->all() === $expected)
        );
    }

    public function test_cards_are_split_into_pages(): void
    {
        $products = Product::factory()->count(HistoryController::PER_PAGE + 1)->create();
        foreach ($products as $i => $product) {
            $this->done($product, now()->subMinutes($i)->toDateTimeString());
        }

        $this->get('/history')->assertInertia(fn (Assert $page) => $page
            ->has('products.data', HistoryController::PER_PAGE)
            ->where('products.lastPage', 2));
        $this->get('/history?page=2')->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }

    /**
     * カードを押すと、その製品の完了した指示が新しい順に出る。始めた日・完了した日・数量・担当（全員）・納期
     */
    public function test_selected_product_shows_its_done_orders_newest_first(): void
    {
        $product = Product::factory()->create(['name' => 'シャフト']);
        $older = $this->done($product, '2026-11-10 17:00:00', ['started_at' => '2026-11-08 08:00:00', 'quantity' => 50, 'due_date' => '2026-11-11']);
        $newer = $this->done($product, '2026-11-15 12:00:00', ['started_at' => '2026-11-14 09:30:00', 'quantity' => 80, 'due_date' => '2026-11-16']);
        $a = User::factory()->create(['name' => '山田 太郎']);
        $b = User::factory()->create(['name' => '佐藤 花子']);
        $newer->assignees()->attach([$a->id, $b->id]);
        // ほかの製品の完了や、まだ完了していない指示は出ない
        $this->done(Product::factory()->create(), '2026-11-16 10:00:00');
        ProductionOrder::factory()->status(OrderStatus::InProgress)->create(['product_id' => $product->id]);

        $this->get("/history?product={$product->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $product->id)
            ->where('selected.name', 'シャフト')
            ->where('selected.total', 2)
            ->has('selected.orders', 2)
            ->where('selected.orders.0', [
                'id' => $newer->id,
                'quantity' => 80,
                'dueDate' => '2026-11-16',
                'startedAt' => '2026-11-14 09:30',
                'completedAt' => '2026-11-15 12:00',
                'assignees' => ['山田 太郎', '佐藤 花子'],
            ])
            ->where('selected.orders.1.id', $older->id)
            ->where('selected.orders.1.assignees', [])
        );
    }

    /**
     * 消した社員も、担当した人として名前が残る（D-036）
     */
    public function test_deleted_members_still_appear_as_assignees(): void
    {
        $product = Product::factory()->create();
        $order = $this->done($product, '2026-11-15 12:00:00');
        $order->assignees()->attach(User::factory()->inactive()->create(['name' => '辞めた人']));

        $this->get("/history?product={$product->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selected.orders.0.assignees', ['辞めた人']));
    }

    public function test_selected_orders_are_limited_to_the_newest(): void
    {
        $product = Product::factory()->create();
        ProductionOrder::factory()->count(HistoryController::ORDERS_LIMIT + 1)->status(OrderStatus::Done)
            ->sequence(fn ($s) => ['completed_at' => now()->subMinutes($s->index)])
            ->create(['product_id' => $product->id]);

        $this->get("/history?product={$product->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selected.total', HistoryController::ORDERS_LIMIT + 1)
            ->has('selected.orders', HistoryController::ORDERS_LIMIT));
    }

    public function test_unknown_product_shows_nothing_selected(): void
    {
        $this->get('/history?product=999999')->assertOk()->assertInertia(fn (Assert $page) => $page->where('selected', null));
    }

    /**
     * 小さな画面から「作業中に戻す」と、完了した日が消え、履歴からいなくなり、ボードの「作業中」に出る（D-024）
     */
    public function test_reopening_from_history_moves_the_order_back_to_the_board(): void
    {
        $product = Product::factory()->create();
        $order = $this->done($product, '2026-11-15 12:00:00', ['started_at' => '2026-11-14 09:30:00']);

        $this->from("/history?product={$product->id}")
            ->patch("/orders/{$order->id}/status", ['status' => 'in_progress'])
            ->assertRedirect("/history?product={$product->id}")
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertNull($order->completed_at);
        $this->assertSame('2026-11-14 09:30:00', $order->started_at->toDateTimeString());

        $this->get("/history?product={$product->id}")->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 0)
            ->where('selected.total', 0));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('columns.1.orders.0.id', $order->id));
    }

    /**
     * 製品や完了した指示が増えても、問い合わせの回数が増えない（担当や取引会社を1件ずつ読まない）。
     * カードの製品も、開いている製品の完了した指示も、両方増やして確かめる
     */
    public function test_query_count_does_not_grow(): void
    {
        $workers = User::factory()->count(3)->create();
        $selected = Product::factory()->create();
        $make = function (int $count) use ($workers, $selected) {
            for ($i = 0; $i < $count; $i++) {
                // カードに出る製品を増やす
                $this->done(Product::factory()->create(), now()->subMinutes($i)->toDateTimeString())
                    ->assignees()->attach($workers->random(2)->pluck('id'));
                // 開いている製品の完了した指示を増やす
                $this->done($selected, now()->subMinutes($i)->toDateTimeString())
                    ->assignees()->attach($workers->random(2)->pluck('id'));
            }
        };

        $make(3);
        $few = $this->countQueries(fn () => $this->get("/history?product={$selected->id}")->assertOk());
        $make(25);
        $many = $this->countQueries(fn () => $this->get("/history?product={$selected->id}")->assertOk());

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
