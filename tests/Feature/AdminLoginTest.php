<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LoginController;
use App\Http\Middleware\EnsureAdminSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * R-06 管理者のパスワード（D-004・D-011）
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $password = 'password')
    {
        return $this->from('/admin/login')->post('/admin/login', ['user_id' => $user->id, 'password' => $password]);
    }

    public function test_admin_pages_require_the_password(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    /**
     * パスワードの画面で選べるのは、使える管理者だけ（作業者と、使えなくした管理者は出ない）
     */
    public function test_login_page_lists_only_active_admins(): void
    {
        $admin = User::factory()->admin()->create(['name' => '管理者A']);
        User::factory()->admin()->inactive()->create(['name' => '辞めた管理者']);
        User::factory()->create(['name' => '作業者']);

        $this->get('/admin/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/Login')
            ->has('admins', 1)
            ->where('admins.0', ['id' => $admin->id, 'name' => '管理者A'])
        );
    }

    public function test_admin_can_log_in_with_the_right_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->login($admin)->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/Home')
            ->where('admin.id', $admin->id)
        );
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->login($admin, 'wrong')->assertSessionHasErrors(['password' => '名前かパスワードがちがいます。']);

        $this->assertGuest();
    }

    /**
     * 作業者は、パスワードがないので管理者として入れない
     */
    public function test_worker_cannot_log_in(): void
    {
        $worker = User::factory()->create();
        // 作業者にパスワードがあったとしても、管理者でなければ入れない
        $workerWithPassword = User::factory()->create(['password' => 'password']);

        $this->login($worker)->assertSessionHasErrors('password');
        $this->login($workerWithPassword)->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $retired = User::factory()->admin()->inactive()->create();

        $this->login($retired)->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_missing_name_or_password_is_rejected(): void
    {
        $this->from('/admin/login')->post('/admin/login', [])
            ->assertSessionHasErrors(['user_id' => '名前を選んでください。', 'password' => 'パスワードを入れてください。']);
    }

    /**
     * パスワードは暗号にして保存する。元のパスワードはデータベースに残らない
     */
    public function test_password_is_stored_hashed(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'secret-pass']);
        $stored = $admin->getAttributes()['password'];

        $this->assertNotSame('secret-pass', $stored);
        $this->assertTrue(Hash::check('secret-pass', $stored));
    }

    /**
     * 10分操作がないと、管理者の操作は自動で終わる（D-011）
     */
    public function test_admin_session_ends_after_ten_minutes_without_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $this->login($admin);

        $this->travel(EnsureAdminSession::IDLE_MINUTES)->minutes();
        $this->travel(1)->seconds();

        $this->get('/admin')
            ->assertRedirect('/admin/login')
            ->assertSessionHas('notice', '10分操作がなかったので、管理者の操作を終えました。もう一度パスワードを入れてください。');
        $this->assertGuest();
    }

    /**
     * 操作をしていれば、10分を過ぎても続けられる（最後に操作してから数える）
     */
    public function test_activity_keeps_the_admin_session_alive(): void
    {
        $admin = User::factory()->admin()->create();
        $this->login($admin);

        $this->travel(9)->minutes();
        $this->get('/admin')->assertOk();
        $this->travel(9)->minutes();
        $this->get('/admin')->assertOk();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_end_the_session(): void
    {
        $admin = User::factory()->admin()->create();
        $this->login($admin);

        $this->post('/admin/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    /**
     * 1分のあいだに5回まちがえたら、正しいパスワードでもしばらく入れない
     */
    public function test_too_many_wrong_passwords_are_blocked_for_a_while(): void
    {
        $admin = User::factory()->admin()->create();

        for ($i = 0; $i < LoginController::MAX_ATTEMPTS; $i++) {
            $this->login($admin, 'wrong');
        }

        $this->login($admin)->assertSessionHasErrors('password');
        $this->assertGuest();

        // 1分たてば、また入れる
        $this->travel(61)->seconds();
        $this->login($admin)->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    /**
     * 作業者の操作（ボードを見る・状態を変えるなど）には、パスワードはいらない（D-023）
     */
    public function test_board_does_not_require_the_password(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('admin', null));
    }
}
