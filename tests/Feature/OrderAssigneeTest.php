<?php

namespace Tests\Feature;

use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * R-11 担当を足す・外す（D-018・D-026）
 */
class OrderAssigneeTest extends TestCase
{
    use RefreshDatabase;

    private function add(ProductionOrder $order, mixed $userId)
    {
        return $this->from('/')->post("/orders/{$order->id}/assignees", ['user_id' => $userId]);
    }

    private function remove(ProductionOrder $order, User $user)
    {
        return $this->from('/')->delete("/orders/{$order->id}/assignees/{$user->id}");
    }

    /**
     * ログインしていなくても、だれでも足せる
     */
    public function test_anyone_can_add_an_assignee(): void
    {
        $order = ProductionOrder::factory()->create();
        $worker = User::factory()->create();
        $this->assertGuest();

        $this->add($order, $worker->id)->assertRedirect('/')->assertSessionHasNoErrors();

        $this->assertSame([$worker->id], $order->assignees()->pluck('users.id')->all());
    }

    public function test_several_people_can_be_added_to_one_order(): void
    {
        $order = ProductionOrder::factory()->create();
        $workers = User::factory()->count(3)->create();

        foreach ($workers as $worker) {
            $this->add($order, $worker->id)->assertSessionHasNoErrors();
        }

        $this->assertSame($workers->pluck('id')->all(), $order->assignees()->pluck('users.id')->all());
    }

    public function test_the_same_person_cannot_be_added_twice(): void
    {
        $order = ProductionOrder::factory()->create();
        $worker = User::factory()->create();
        $this->add($order, $worker->id);

        $this->add($order, $worker->id)->assertSessionHasErrors(['user_id' => 'その社員は、もう担当に入っています。']);

        $this->assertSame(1, $order->assignees()->count());
    }

    /**
     * 同じ人を、ほかの指示の担当にするのはよい
     */
    public function test_the_same_person_can_be_assigned_to_different_orders(): void
    {
        $worker = User::factory()->create();
        [$first, $second] = ProductionOrder::factory()->count(2)->create()->all();

        $this->add($first, $worker->id)->assertSessionHasNoErrors();
        $this->add($second, $worker->id)->assertSessionHasNoErrors();

        $this->assertSame(2, $worker->assignedOrders()->count());
    }

    /**
     * 使えなくした社員（辞めた人）は足せない（R-07）
     */
    public function test_an_inactive_member_cannot_be_added(): void
    {
        $order = ProductionOrder::factory()->create();
        $retired = User::factory()->inactive()->create();

        $this->add($order, $retired->id)->assertSessionHasErrors(['user_id' => '選んだ社員は、今は担当に足せません。']);

        $this->assertSame(0, $order->assignees()->count());
    }

    public function test_unknown_or_missing_member_is_rejected(): void
    {
        $order = ProductionOrder::factory()->create();

        $this->add($order, 999999)->assertSessionHasErrors('user_id');
        $this->add($order, 'abc')->assertSessionHasErrors('user_id');
        $this->from('/')->post("/orders/{$order->id}/assignees", [])->assertSessionHasErrors('user_id');

        $this->assertSame(0, $order->assignees()->count());
    }

    public function test_anyone_can_remove_an_assignee_and_others_stay(): void
    {
        $order = ProductionOrder::factory()->create();
        [$stay, $leave] = User::factory()->count(2)->create()->all();
        $order->assignees()->attach([$stay->id, $leave->id]);

        $this->remove($order, $leave)->assertRedirect('/')->assertSessionHasNoErrors();

        $this->assertSame([$stay->id], $order->assignees()->pluck('users.id')->all());
        // 社員そのものは消えない
        $this->assertModelExists($leave);
    }

    /**
     * 使えなくした社員でも、担当から外すことはできる
     */
    public function test_an_inactive_member_can_still_be_removed(): void
    {
        $order = ProductionOrder::factory()->create();
        $retired = User::factory()->inactive()->create();
        $order->assignees()->attach($retired);

        $this->remove($order, $retired)->assertSessionHasNoErrors();

        $this->assertSame(0, $order->assignees()->count());
    }

    /**
     * 2台の端末で続けて外したとき、あとの人には「先に変わっていた」と出す
     */
    public function test_removing_someone_who_is_not_assigned_shows_a_message(): void
    {
        $order = ProductionOrder::factory()->create();
        $worker = User::factory()->create(['name' => '山田 太郎']);

        $this->remove($order, $worker)->assertSessionHasErrors([
            'user_id' => '山田 太郎さんは、もう担当から外れていました。ほかの端末で先に変わっていました。',
        ]);
    }

    /**
     * ボードのプルダウンには、使えなくした社員は出さない
     */
    public function test_board_members_exclude_inactive_people(): void
    {
        $active = User::factory()->create(['name' => '今いる人']);
        User::factory()->inactive()->create(['name' => '辞めた人']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('members', 1)
            ->where('members.0', ['id' => $active->id, 'name' => '今いる人'])
        );
    }

    /**
     * 使えなくした社員も、もう担当に入っている指示では名前が出る（過去の記録を残す）
     */
    public function test_inactive_assignee_is_still_shown_on_the_card(): void
    {
        $order = ProductionOrder::factory()->create();
        $retired = User::factory()->inactive()->create(['name' => '辞めた人']);
        $order->assignees()->attach($retired);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.orders.0.assignees.0.name', '辞めた人')
        );
    }
}
