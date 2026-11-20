<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Middleware\EnsureAdminSession;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-03 管理者が指示を登録・削除する（D-004・D-027）
 */
class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(['name' => '管理者A']))
            ->withSession(['admin_last_activity' => now()->getTimestamp()]);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function input(array $changes = []): array
    {
        return [
            'product_id' => Product::factory()->create()->id,
            'quantity' => 120,
            'material_ready_date' => '2026-11-25',
            'due_date' => '2026-11-30',
            'assignee_ids' => [],
            ...$changes,
        ];
    }

    public function test_create_and_delete_require_the_admin_password(): void
    {
        $order = ProductionOrder::factory()->create();

        $this->get('/admin/orders/create')->assertRedirect('/admin/login');
        $this->post('/admin/orders', $this->input())->assertRedirect('/admin/login');
        $this->delete("/admin/orders/{$order->id}")->assertRedirect('/admin/login');

        $this->assertSame(1, ProductionOrder::count());
    }

    public function test_worker_cannot_create_or_delete(): void
    {
        $order = ProductionOrder::factory()->create();
        $this->actingAs(User::factory()->create())->withSession(['admin_last_activity' => now()->getTimestamp()]);

        $this->post('/admin/orders', $this->input())->assertRedirect('/admin/login');
        $this->delete("/admin/orders/{$order->id}")->assertRedirect('/admin/login');

        $this->assertSame(1, ProductionOrder::count());
    }

    /**
     * 登録の画面には、取引会社ごとの製品と、使える社員だけが出る
     */
    public function test_create_page_lists_companies_with_their_products_and_active_members(): void
    {
        $company = Company::factory()->create(['name' => 'ダミー東和工業']);
        $product = Product::factory()->create(['company_id' => $company->id, 'code' => 'SH-0660', 'name' => 'シャフト']);
        $worker = User::factory()->create(['name' => '今いる人']);
        User::factory()->inactive()->create(['name' => '辞めた人']);

        $this->asAdmin()->get('/admin/orders/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/OrderCreate')
            ->where('companies', [[
                'id' => $company->id,
                'name' => 'ダミー東和工業',
                'products' => [['id' => $product->id, 'code' => 'SH-0660', 'name' => 'シャフト']],
            ]])
            ->where('members', fn ($members) => collect($members)->pluck('name')->contains('今いる人')
                && ! collect($members)->pluck('name')->contains('辞めた人'))
        );
    }

    /**
     * 登録した指示は未着手で、担当も入り、ボードの「未着手」に出る
     */
    public function test_admin_can_create_an_order_with_assignees_and_it_appears_on_the_board(): void
    {
        [$a, $b] = User::factory()->count(2)->create()->all();
        $input = $this->input(['assignee_ids' => [$a->id, $b->id]]);

        $this->asAdmin()->from('/admin/orders/create')->post('/admin/orders', $input)
            ->assertRedirect('/admin/orders/create')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '指示を登録しました。ボードの「未着手」に出ています。');

        $order = ProductionOrder::sole();
        $this->assertSame($input['product_id'], $order->product_id);
        $this->assertSame(120, $order->quantity);
        $this->assertSame('2026-11-25', $order->material_ready_date->toDateString());
        $this->assertSame('2026-11-30', $order->due_date->toDateString());
        $this->assertSame(OrderStatus::NotStarted, $order->status);
        $this->assertNull($order->started_at);
        $this->assertFalse($order->is_dummy);
        $this->assertSame([$a->id, $b->id], $order->assignees()->pluck('users.id')->all());

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('columns.0.orders.0.id', $order->id));
    }

    public function test_order_can_be_created_without_material_date_or_assignees(): void
    {
        $this->asAdmin()->from('/admin/orders/create')
            ->post('/admin/orders', $this->input(['material_ready_date' => null, 'assignee_ids' => []]))
            ->assertSessionHasNoErrors();

        $order = ProductionOrder::sole();
        $this->assertNull($order->material_ready_date);
        $this->assertSame(0, $order->assignees()->count());
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
            '納期がない' => [['due_date' => null], 'due_date'],
            '納期の形がちがう' => [['due_date' => '11/30'], 'due_date'],
            '材料集結日の形がちがう' => [['material_ready_date' => '2026/11/25'], 'material_ready_date'],
            '担当にいない社員' => [['assignee_ids' => [999999]], 'assignee_ids.0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_and_nothing_is_created(array $changes, string $field): void
    {
        $this->asAdmin()->from('/admin/orders/create')->post('/admin/orders', $this->input($changes))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, ProductionOrder::count());
    }

    public function test_inactive_or_duplicate_assignees_are_rejected(): void
    {
        $worker = User::factory()->create();
        $retired = User::factory()->inactive()->create();

        $this->asAdmin()->from('/admin/orders/create')
            ->post('/admin/orders', $this->input(['assignee_ids' => [$retired->id]]))
            ->assertSessionHasErrors(['assignee_ids.0' => '担当に入れられない社員がいます。']);
        $this->post('/admin/orders', $this->input(['assignee_ids' => [$worker->id, $worker->id]]))
            ->assertSessionHasErrors(['assignee_ids.0' => '同じ人が二重に入っています。']);

        $this->assertSame(0, ProductionOrder::count());
    }

    public function test_admin_can_delete_an_order_and_its_assignees_go_with_it(): void
    {
        $order = ProductionOrder::factory()->withAssignee()->create();
        $keep = ProductionOrder::factory()->create();

        $this->asAdmin()->from('/')->delete("/admin/orders/{$order->id}")->assertRedirect('/');

        $this->assertModelMissing($order);
        $this->assertModelExists($keep);
        $this->assertDatabaseMissing('order_assignees', ['production_order_id' => $order->id]);
        // 社員そのものは消えない
        $this->assertSame(1, User::where('role', 'worker')->count());
    }

    /**
     * ボードでは、管理者で入っていて10分たっていないときだけ、管理者の名前が渡る（「この指示を消す」を出す）
     */
    public function test_board_knows_the_admin_only_while_the_admin_session_is_fresh(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('admin', null));

        $this->asAdmin()->get('/')->assertInertia(fn (Assert $page) => $page->where('admin.name', '管理者A'));

        $this->travel(EnsureAdminSession::IDLE_MINUTES)->minutes();
        $this->travel(1)->seconds();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('admin', null));
    }

    /**
     * 10分たったあとに消そうとしても、消えない
     */
    public function test_delete_after_ten_minutes_is_rejected(): void
    {
        $order = ProductionOrder::factory()->create();
        $this->asAdmin();

        $this->travel(EnsureAdminSession::IDLE_MINUTES)->minutes();
        $this->travel(1)->seconds();
        $this->delete("/admin/orders/{$order->id}")->assertRedirect('/admin/login');

        $this->assertModelExists($order);
    }
}
