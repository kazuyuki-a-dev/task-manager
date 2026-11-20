<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 管理者の画面の「最後のバックアップ」（R-09・D-046）
 */
class AdminHomeBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // テストのたびに、まだない保存先を使う
        $this->dir = storage_path('framework/testing/backups-'.uniqid());
        config(['backup.path' => $this->dir, 'backup.stale_hours' => 25]);
        $this->travelTo(Carbon::parse('2026-11-20 15:30:00'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->withSession(['admin_last_activity' => now()->getTimestamp()]);
    }

    /**
     * @param  list<string>  $names
     */
    private function placeBackups(array $names): void
    {
        File::ensureDirectoryExists($this->dir);
        foreach ($names as $name) {
            File::put($this->dir.'/'.$name, 'x');
        }
    }

    public function test_no_backup_folder_shows_nothing_yet(): void
    {
        $this->assertDirectoryDoesNotExist($this->dir);

        $this->asAdmin()->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/Home')
            ->where('backup', null));
    }

    /**
     * バックアップの名前の形でないファイルしかないときも、「まだない」
     */
    public function test_files_that_are_not_backups_are_ignored(): void
    {
        $this->placeBackups(['memo.txt', 'task-manager-old.sql.gz']);

        $this->asAdmin()->get('/admin')->assertInertia(fn (Assert $page) => $page->where('backup', null));
    }

    /**
     * いくつかあるときは、名前の日時がいちばん新しいものを出す（ファイルを置いた順ではない）
     */
    public function test_the_newest_backup_by_name_is_shown(): void
    {
        $this->placeBackups([
            'task-manager-20261120-123000.sql.gz',
            'task-manager-20261118-123000.sql.gz',
            'task-manager-20261119-123000.sql.gz',
            'memo.txt',
        ]);

        $this->asAdmin()->get('/admin')->assertInertia(fn (Assert $page) => $page->where('backup', [
            'fileName' => 'task-manager-20261120-123000.sql.gz',
            'takenAt' => '2026-11-20 12:30',
            'hoursAgo' => 3,
            'isStale' => false,
        ]));
    }

    /**
     * 25時間までは赤くない。25時間をこえたら赤く知らせる
     *
     * @return array<string, array{string, bool, int}>
     */
    public static function ages(): array
    {
        return [
            '24時間前' => ['task-manager-20261119-153000.sql.gz', false, 24],
            'ちょうど25時間前' => ['task-manager-20261119-143000.sql.gz', false, 25],
            '25時間と1秒前' => ['task-manager-20261119-142959.sql.gz', true, 25],
            '3日前' => ['task-manager-20261117-123000.sql.gz', true, 75],
        ];
    }

    #[DataProvider('ages')]
    public function test_old_backup_is_flagged_after_25_hours(string $name, bool $isStale, int $hoursAgo): void
    {
        $this->placeBackups([$name]);

        $this->asAdmin()->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('backup.isStale', $isStale)
            ->where('backup.hoursAgo', $hoursAgo));
    }

    /**
     * バックアップをとったら、管理者の画面にすぐ出る（コマンドと画面が同じ読み方をしている）
     */
    public function test_a_new_backup_appears_on_the_admin_page(): void
    {
        $this->artisan('backup:database')->assertSuccessful();

        $this->asAdmin()->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('backup.fileName', 'task-manager-20261120-153000.sql.gz')
            ->where('backup.isStale', false));
    }

    public function test_only_admins_can_see_it(): void
    {
        $this->placeBackups(['task-manager-20261120-123000.sql.gz']);

        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
