<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-02 期限の見える化（D-008）
 * 「今日」を決めて、日付をずらしながら確かめる
 */
class DeadlineTest extends TestCase
{
    use RefreshDatabase;

    private function orderDueOn(string $dueDate, OrderStatus $status = OrderStatus::NotStarted, string $name = '製品'): ProductionOrder
    {
        return ProductionOrder::factory()->status($status)->create([
            'product_id' => Product::factory()->create(['name' => $name]),
            'due_date' => $dueDate,
        ]);
    }

    /**
     * 要件定義書の例：納期 11/22 なら 11/20〜11/22 が期限間近、11/23 から遅れ
     *
     * @return array<string, array{string, int, ?string}>
     */
    public static function daysAroundDueDate(): array
    {
        return [
            '3日前（11/19）はどちらでもない' => ['2026-11-19 12:00', 3, null],
            '2日前（11/20）は期限間近' => ['2026-11-20 12:00', 2, 'soon'],
            '1日前（11/21）は期限間近' => ['2026-11-21 12:00', 1, 'soon'],
            '当日（11/22）は期限間近' => ['2026-11-22 12:00', 0, 'soon'],
            '翌日（11/23）は遅れ' => ['2026-11-23 12:00', -1, 'overdue'],
            '5日後（11/27）も遅れ' => ['2026-11-27 12:00', -5, 'overdue'],
        ];
    }

    #[DataProvider('daysAroundDueDate')]
    public function test_deadline_depends_on_days_left_to_due_date(string $now, int $daysLeft, ?string $deadline): void
    {
        $this->orderDueOn('2026-11-22');
        $this->travelTo(Carbon::parse($now));

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.daysLeft', $daysLeft)
            ->where('columns.0.orders.0.deadline', $deadline)
        );
    }

    /**
     * 日本時間の夜中0時で切りかわる（世界標準時の0時＝日本の9時ではない。D-022）
     */
    public function test_deadline_switches_at_midnight_japan_time(): void
    {
        $this->orderDueOn('2026-11-22');

        $this->travelTo(Carbon::parse('2026-11-22 23:59:59', 'Asia/Tokyo'));
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.deadline', 'soon')
            ->where('today', '2026-11-22')
        );

        $this->travelTo(Carbon::parse('2026-11-23 00:00:00', 'Asia/Tokyo'));
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.deadline', 'overdue')
            ->where('today', '2026-11-23')
        );
    }

    public function test_in_progress_orders_are_also_highlighted(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00'));
        $this->orderDueOn('2026-11-19', OrderStatus::InProgress);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.1.orders.0.deadline', 'overdue')
        );
    }

    /**
     * いちばん上のまとめには、遅れを先に、そのあと期限間近を、それぞれ納期の近い順に出す。
     * どちらでもない指示と、完了した指示は出さない
     */
    public function test_alerts_list_overdue_first_then_soon_and_skip_others(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00'));
        $this->orderDueOn('2026-11-21', name: '期限間近・あと1日');
        $this->orderDueOn('2026-11-30', name: 'どちらでもない');
        $this->orderDueOn('2026-11-19', OrderStatus::InProgress, name: '遅れ・1日');
        $this->orderDueOn('2026-11-20', name: '期限間近・今日まで');
        $this->orderDueOn('2026-11-15', name: '遅れ・5日');
        $this->orderDueOn('2026-11-10', OrderStatus::Done, name: '完了');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('alerts', 4)
            ->where('alerts.0.productName', '遅れ・5日')
            ->where('alerts.1.productName', '遅れ・1日')
            ->where('alerts.2.productName', '期限間近・今日まで')
            ->where('alerts.3.productName', '期限間近・あと1日')
        );
    }

    public function test_alerts_are_empty_when_nothing_is_late_or_due_soon(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00'));
        $this->orderDueOn('2026-11-30');

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('alerts', 0));
    }

    /**
     * 材料集結日が過ぎていても、目立たせない（D-009）
     */
    public function test_material_ready_date_does_not_affect_deadline(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00'));
        ProductionOrder::factory()->create([
            'material_ready_date' => '2026-11-01',
            'due_date' => '2026-11-30',
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.deadline', null)
            ->has('alerts', 0)
        );
    }
}
