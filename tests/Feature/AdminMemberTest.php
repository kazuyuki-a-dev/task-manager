<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-07 社員の登録（D-036）
 */
class AdminMemberTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    /**
     * 管理者としてパスワードを入れた状態にする
     */
    private function asAdmin(): static
    {
        $this->me = User::factory()->admin()->create(['name' => '自分']);

        return $this->actingAs($this->me)->withSession(['admin_last_activity' => now()->getTimestamp()]);
    }

    public function test_member_pages_require_the_admin_password(): void
    {
        $worker = User::factory()->create();

        $this->get('/admin/members')->assertRedirect('/admin/login');
        $this->post('/admin/members', ['name' => '新人', 'role' => 'worker'])->assertRedirect('/admin/login');
        $this->patch("/admin/members/{$worker->id}", ['name' => '直した', 'role' => 'worker'])->assertRedirect('/admin/login');
        $this->delete("/admin/members/{$worker->id}")->assertRedirect('/admin/login');

        $this->assertSame(1, User::count());
        $this->assertTrue($worker->refresh()->is_active);
    }

    /**
     * 一覧には、消していない社員だけが出る。管理者が先
     */
    public function test_list_shows_active_members_with_admins_first(): void
    {
        $this->asAdmin();
        $worker = User::factory()->create(['name' => '作業者A']);
        User::factory()->inactive()->create(['name' => '消した人']);

        $this->get('/admin/members')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/Members')
            ->has('members', 2)
            ->where('members.0', ['id' => $this->me->id, 'name' => '自分', 'role' => 'admin', 'isDummy' => false, 'isSelf' => true])
            ->where('members.1', ['id' => $worker->id, 'name' => '作業者A', 'role' => 'worker', 'isDummy' => false, 'isSelf' => false])
        );
    }

    public function test_admin_can_add_a_worker_without_password(): void
    {
        $this->asAdmin()->from('/admin/members')->post('/admin/members', ['name' => '新人', 'role' => 'worker', 'password' => 'ignored-pass'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '新人さんを作業者として登録しました。');

        $member = User::where('name', '新人')->sole();
        $this->assertSame(UserRole::Worker, $member->role);
        // 作業者はパスワードを持たない（送られてきても入れない）
        $this->assertNull($member->password);
        $this->assertTrue($member->is_active);
    }

    public function test_admin_can_add_another_admin_who_can_then_log_in(): void
    {
        $this->asAdmin()->from('/admin/members')->post('/admin/members', ['name' => '管理者B', 'role' => 'admin', 'password' => 'long-enough'])
            ->assertSessionHasNoErrors();

        $admin = User::where('name', '管理者B')->sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('long-enough', $admin->password));

        // 足した管理者で入れる
        $this->post('/admin/logout');
        $this->from('/admin/login')->post('/admin/login', ['user_id' => $admin->id, 'password' => 'long-enough'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_new_admin_needs_a_password_of_at_least_eight_characters(): void
    {
        $this->asAdmin()->from('/admin/members')->post('/admin/members', ['name' => '管理者B', 'role' => 'admin', 'password' => ''])
            ->assertSessionHasErrors(['password' => '管理者にするときは、パスワードを決めてください。']);
        $this->post('/admin/members', ['name' => '管理者B', 'role' => 'admin', 'password' => '1234567'])
            ->assertSessionHasErrors(['password' => 'パスワードは8文字以上にしてください。']);
        $this->post('/admin/members', ['name' => '', 'role' => 'worker'])->assertSessionHasErrors(['name' => '名前を入れてください。']);
        $this->post('/admin/members', ['name' => 'だれか', 'role' => 'boss'])->assertSessionHasErrors('role');

        $this->assertSame(1, User::count());
    }

    public function test_admin_can_rename_a_member(): void
    {
        $this->asAdmin();
        $worker = User::factory()->create(['name' => '山田 太郎']);

        $this->from('/admin/members')->patch("/admin/members/{$worker->id}", ['name' => '山田 一郎', 'role' => 'worker'])
            ->assertSessionHasNoErrors();

        $this->assertSame('山田 一郎', $worker->refresh()->name);
    }

    /**
     * 作業者を管理者にするときは、パスワードを決める
     */
    public function test_worker_can_be_promoted_to_admin_with_a_password(): void
    {
        $this->asAdmin();
        $worker = User::factory()->create();

        $this->from('/admin/members')->patch("/admin/members/{$worker->id}", ['name' => $worker->name, 'role' => 'admin'])
            ->assertSessionHasErrors(['password' => '管理者にするときは、パスワードを決めてください。']);
        $this->assertSame(UserRole::Worker, $worker->refresh()->role);

        $this->patch("/admin/members/{$worker->id}", ['name' => $worker->name, 'role' => 'admin', 'password' => 'long-enough'])
            ->assertSessionHasNoErrors();

        $worker->refresh();
        $this->assertSame(UserRole::Admin, $worker->role);
        $this->assertTrue(Hash::check('long-enough', $worker->password));
    }

    /**
     * 管理者のパスワードは、空なら今のまま。入れれば変わる
     */
    public function test_admin_password_can_be_changed_or_kept(): void
    {
        $this->asAdmin();
        $other = User::factory()->admin()->create(['password' => 'old-password']);

        $this->from('/admin/members')->patch("/admin/members/{$other->id}", ['name' => '名前だけ直す', 'role' => 'admin', 'password' => null])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('old-password', $other->refresh()->password));

        $this->patch("/admin/members/{$other->id}", ['name' => '名前だけ直す', 'role' => 'admin', 'password' => 'new-password'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $other->refresh()->password));
    }

    /**
     * 管理者を作業者にすると、パスワードは消える（作業者はパスワードを持たない）
     */
    public function test_admin_can_be_demoted_and_loses_the_password(): void
    {
        $this->asAdmin();
        $other = User::factory()->admin()->create();

        $this->from('/admin/members')->patch("/admin/members/{$other->id}", ['name' => $other->name, 'role' => 'worker'])
            ->assertSessionHasNoErrors();

        $other->refresh();
        $this->assertSame(UserRole::Worker, $other->role);
        $this->assertNull($other->password);
    }

    public function test_cannot_demote_yourself(): void
    {
        $this->asAdmin();
        User::factory()->admin()->create();

        $this->from('/admin/members')->patch("/admin/members/{$this->me->id}", ['name' => '自分', 'role' => 'worker'])
            ->assertSessionHasErrors(['role' => '自分自身を作業者にすることはできません。ほかの管理者にたのんでください。']);

        $this->assertSame(UserRole::Admin, $this->me->refresh()->role);
    }

    public function test_cannot_delete_yourself(): void
    {
        $this->asAdmin();
        User::factory()->admin()->create();

        $this->from('/admin/members')->delete("/admin/members/{$this->me->id}")
            ->assertSessionHasErrors(['member' => '自分自身を消すことはできません。ほかの管理者にたのんでください。']);

        $this->assertTrue($this->me->refresh()->is_active);
    }

    /**
     * 最後の1人の管理者は、消したり作業者にしたりできない。
     * ふつうは自分が残るので起きないが、2人の管理者が同時におたがいを消そうとしたときに働く。
     * 「自分は画面を開いたときは管理者だったが、そのあいだにほかの端末で消されていた」場面をまねる
     *
     * @return array<string, array{string}>
     */
    public static function lastAdminActions(): array
    {
        return ['消す' => ['delete'], '作業者にする' => ['demote']];
    }

    #[DataProvider('lastAdminActions')]
    public function test_cannot_remove_the_last_admin(string $action): void
    {
        $this->asAdmin();
        $other = User::factory()->admin()->create(['name' => '管理者B']);
        // ほかの端末で、自分が先に消されていた（使える管理者は $other だけになった）
        User::whereKey($this->me->id)->update(['is_active' => false]);

        $response = $action === 'delete'
            ? $this->from('/admin/members')->delete("/admin/members/{$other->id}")
            : $this->from('/admin/members')->patch("/admin/members/{$other->id}", ['name' => '管理者B', 'role' => 'worker']);

        $field = $action === 'delete' ? 'member' : 'role';
        $verb = $action === 'delete' ? '消す' : '作業者にする';
        $response->assertSessionHasErrors([$field => "管理者が1人だけなので、{$verb}ことはできません。先にほかの管理者を足してください。"]);

        $other->refresh();
        $this->assertTrue($other->is_active);
        $this->assertSame(UserRole::Admin, $other->role);
    }

    /**
     * ほかに管理者がいれば、別の管理者を消せる（消した管理者は数えない）
     */
    public function test_another_admin_can_be_deleted_when_admins_remain(): void
    {
        $this->asAdmin();
        User::factory()->admin()->inactive()->create();
        $other = User::factory()->admin()->create();

        $this->from('/admin/members')->delete("/admin/members/{$other->id}")->assertSessionHasNoErrors();

        $this->assertFalse($other->refresh()->is_active);
        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
    }

    public function test_deleted_member_disappears_from_lists_but_stays_in_records(): void
    {
        $this->asAdmin();
        $worker = User::factory()->create(['name' => '辞める人']);
        $order = ProductionOrder::factory()->status(OrderStatus::InProgress)->create();
        $order->assignees()->attach($worker);

        $this->from('/admin/members')->delete("/admin/members/{$worker->id}")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '辞める人さんを消しました。');

        // データは残し、印を付ける
        $this->assertModelExists($worker);
        $this->assertFalse($worker->refresh()->is_active);

        // 社員の一覧と、担当を足すプルダウンには出ない
        $this->get('/admin/members')->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => ! collect($members)->pluck('name')->contains('辞める人')));
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => ! collect($members)->pluck('name')->contains('辞める人'))
            // 過去の指示には名前が残る
            ->where('columns.1.orders.0.assignees.0.name', '辞める人'));
    }

    public function test_deleted_admin_cannot_log_in(): void
    {
        $this->asAdmin();
        $other = User::factory()->admin()->create(['password' => 'other-pass']);

        $this->from('/admin/members')->delete("/admin/members/{$other->id}")->assertSessionHasNoErrors();
        $this->post('/admin/logout');

        $this->get('/admin/login')->assertInertia(fn (Assert $page) => $page
            ->where('admins', fn ($admins) => ! collect($admins)->pluck('id')->contains($other->id)));
        $this->from('/admin/login')->post('/admin/login', ['user_id' => $other->id, 'password' => 'other-pass'])
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_deleted_member_cannot_be_edited_or_deleted_again(): void
    {
        $this->asAdmin();
        $gone = User::factory()->inactive()->create();

        $this->patch("/admin/members/{$gone->id}", ['name' => '直す', 'role' => 'worker'])->assertNotFound();
        $this->delete("/admin/members/{$gone->id}")->assertNotFound();
    }
}
