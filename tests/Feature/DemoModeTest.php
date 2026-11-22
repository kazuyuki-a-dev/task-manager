<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * お試し用のデモ（DEMO_MODE・D-048）。デモのときと、デモでないとき（手元の開発・会社の本番）の両方を確かめる
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private const BANNER = 'お試し用のデモです。会社・製品・人の名前はすべてダミーです。触った内容は、しばらくすると元に戻ります';

    private function demo(bool $on): void
    {
        config(['demo.enabled' => $on]);
    }

    /**
     * DatabaseSeeder と同じ、ダミー管理者
     */
    private function demoAdmin(): User
    {
        return User::factory()->admin()->dummy()->create(['name' => 'ダミー管理者']);
    }

    /**
     * 管理者としてパスワードを入れた状態にする
     */
    private function asAdmin(User $admin): static
    {
        return $this->actingAs($admin)->withSession(['admin_last_activity' => now()->getTimestamp()]);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function modes(): array
    {
        return [
            'デモ' => [true],
            'デモでない' => [false],
        ];
    }

    /**
     * 帯は、デモのときだけ、どの画面にも出る
     */
    #[DataProvider('modes')]
    public function test_banner_is_shown_on_every_page_only_in_demo(bool $on): void
    {
        $this->demo($on);

        foreach (['/', '/history', '/calendar', '/admin/login'] as $url) {
            $response = $this->get($url)->assertOk();
            $on ? $response->assertSee(self::BANNER) : $response->assertDontSee(self::BANNER);
        }
    }

    /**
     * 画面には、デモかどうかを渡す
     */
    #[DataProvider('modes')]
    public function test_pages_know_whether_it_is_a_demo(bool $on): void
    {
        $this->demo($on);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('demo', $on));
    }

    /**
     * ログインの画面の入り方は、デモのときだけ出す
     */
    public function test_login_page_shows_how_to_enter_only_in_demo(): void
    {
        $this->demoAdmin();

        $this->demo(true);
        $this->get('/admin/login')->assertInertia(fn (Assert $page) => $page
            ->where('demoLogin', ['name' => 'ダミー管理者', 'password' => 'password']));

        $this->demo(false);
        $this->get('/admin/login')->assertInertia(fn (Assert $page) => $page->where('demoLogin', null));
    }

    /**
     * ログインの画面に出した入り方で、本当に入れる
     */
    public function test_the_shown_login_works(): void
    {
        $this->demo(true);
        $admin = $this->demoAdmin();

        $this->post('/admin/login', ['user_id' => $admin->id, 'password' => config('demo.admin_password')])
            ->assertRedirect('/admin');
    }

    /**
     * デモのとき、ダミー管理者の名前・パスワード・役割は変えられず、消せない
     *
     * @return array<string, array{array<string, string>, string}>
     */
    public static function blockedChanges(): array
    {
        return [
            '名前を変える' => [['name' => 'いたずら', 'role' => 'admin'], 'name'],
            'パスワードを変える' => [['name' => 'ダミー管理者', 'role' => 'admin', 'password' => 'changed-password'], 'password'],
            '作業者にする' => [['name' => 'ダミー管理者', 'role' => 'worker'], 'role'],
        ];
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('blockedChanges')]
    public function test_demo_admin_cannot_be_changed_in_demo(array $input, string $field): void
    {
        $this->demo(true);
        $demoAdmin = $this->demoAdmin();
        // ほかの管理者として入る（自分自身を作業者にする・消すの決まりとは、べつに確かめるため）
        $other = User::factory()->admin()->create(['name' => 'ほかの管理者']);

        $this->asAdmin($other)->from('/admin/members')->patch("/admin/members/{$demoAdmin->id}", $input)
            ->assertSessionHasErrors($field);

        $demoAdmin->refresh();
        $this->assertSame('ダミー管理者', $demoAdmin->name);
        $this->assertSame(UserRole::Admin, $demoAdmin->role);
        $this->assertTrue(Hash::check('password', $demoAdmin->password));
    }

    public function test_demo_admin_cannot_be_deleted_in_demo(): void
    {
        $this->demo(true);
        $demoAdmin = $this->demoAdmin();
        $other = User::factory()->admin()->create(['name' => 'ほかの管理者']);

        $this->asAdmin($other)->from('/admin/members')->delete("/admin/members/{$demoAdmin->id}")
            ->assertSessionHasErrors('member');

        $this->assertTrue($demoAdmin->refresh()->is_active);
    }

    /**
     * 何も変えずに保存するのはよい（小さな画面で「保存」を押しただけのとき）
     */
    public function test_saving_demo_admin_without_changes_is_allowed_in_demo(): void
    {
        $this->demo(true);
        $demoAdmin = $this->demoAdmin();

        $this->asAdmin($demoAdmin)->from('/admin/members')->patch("/admin/members/{$demoAdmin->id}", ['name' => 'ダミー管理者', 'role' => 'admin'])
            ->assertSessionHasNoErrors();
    }

    /**
     * デモでも、ほかの管理者（見に来た人が足した管理者）は、今までどおり直せる・消せる
     */
    public function test_other_admins_can_still_be_changed_in_demo(): void
    {
        $this->demo(true);
        $demoAdmin = $this->demoAdmin();
        $added = User::factory()->admin()->dummy()->create(['name' => '足した管理者']);

        $this->asAdmin($demoAdmin)->from('/admin/members')->patch("/admin/members/{$added->id}", ['name' => '直した管理者', 'role' => 'admin', 'password' => 'new-password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('直した管理者', $added->refresh()->name);

        $this->delete("/admin/members/{$added->id}")->assertSessionHasNoErrors();
        $this->assertFalse($added->refresh()->is_active);
    }

    /**
     * デモでないときは、ダミー管理者も、ほかの管理者と同じように直せる・消せる
     */
    public function test_demo_admin_is_not_protected_outside_demo(): void
    {
        $this->demo(false);
        $demoAdmin = $this->demoAdmin();
        $other = User::factory()->admin()->create(['name' => 'ほかの管理者']);

        $this->asAdmin($other)->from('/admin/members')->patch("/admin/members/{$demoAdmin->id}", ['name' => '直した', 'role' => 'admin', 'password' => 'changed-password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('直した', $demoAdmin->refresh()->name);

        $this->delete("/admin/members/{$demoAdmin->id}")->assertSessionHasNoErrors();
        $this->assertFalse($demoAdmin->refresh()->is_active);
    }

    /**
     * デモのとき、管理者の画面には「止めています」と出し、バックアップのフォルダは見ない
     */
    public function test_admin_home_shows_backup_is_stopped_only_in_demo(): void
    {
        $admin = $this->demoAdmin();

        $this->demo(true);
        $this->asAdmin($admin)->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('backupStopped', true)
            ->where('backup', null));

        $this->demo(false);
        $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('backupStopped', false));
    }

    /**
     * デモのとき、バックアップと戻すコマンドは、何もしない（保存先のフォルダも作らない）
     */
    public function test_backup_commands_do_nothing_in_demo(): void
    {
        $this->demo(true);
        $dir = storage_path('framework/testing/backups-'.uniqid());
        config(['backup.path' => $dir]);

        $this->artisan('backup:database')->expectsOutput('お試し用のデモでは、バックアップを止めています')->assertSuccessful();
        $this->artisan('backup:database --if-due')->assertSuccessful();
        $this->artisan('backup:restore', ['file' => 'task-manager-20261120-123000.sql.gz', '--force' => true])
            ->expectsOutput('お試し用のデモでは、バックアップから戻すのを止めています')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($dir);
        File::deleteDirectory($dir);
    }

    /**
     * 定期実行のバックアップは、デモのときだけ動かさない
     */
    #[DataProvider('modes')]
    public function test_scheduled_backup_is_skipped_only_in_demo(bool $on): void
    {
        $this->demo($on);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'backup:database'));

        $this->assertNotNull($event);
        $this->assertSame(! $on, $event->filtersPass($this->app));
    }
}
