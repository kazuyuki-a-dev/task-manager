<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * R-09 毎日のバックアップ（D-044）
 */
class BackupCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // テストのたびに、まだない保存先を使う（フォルダがなければ作ることも確かめる）
        $this->dir = storage_path('framework/testing/backups-'.uniqid());
        config(['backup.path' => $this->dir, 'backup.keep_days' => 30]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function backups(): array
    {
        return collect(File::files($this->dir))->map(fn ($f) => $f->getFilename())->sort()->values()->all();
    }

    /**
     * バックアップをとると、保存先（なければ作る）に .sql.gz ができ、中身にテーブルの作り方が入っている
     */
    public function test_backup_writes_a_gzipped_dump_into_a_new_folder(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:30:00'));
        $this->assertDirectoryDoesNotExist($this->dir);

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertSame(['task-manager-20261120-123000.sql.gz'], $this->backups());
        $sql = gzdecode(File::get($this->dir.'/task-manager-20261120-123000.sql.gz'));
        foreach (['production_orders', 'products', 'companies', 'users', 'order_assignees', 'order_logs', 'absences', 'calendar_events', 'company_holidays'] as $table) {
            $this->assertStringContainsString("CREATE TABLE `{$table}`", $sql);
        }
    }

    /**
     * 残す日数より古いバックアップは消える。新しいものと、バックアップの名前の形でないファイルは残る
     */
    public function test_old_backups_are_deleted_and_others_are_kept(): void
    {
        File::ensureDirectoryExists($this->dir);
        $this->travelTo(Carbon::parse('2026-11-20 12:30:00'));
        foreach ([
            // 11/20 の30日前は 10/21（10月は31日まであるため）
            'task-manager-20261021-015959.sql.gz', // 30日と1秒前 → 消える
            'task-manager-20261021-123000.sql.gz', // ちょうど30日前 → 残る
            'task-manager-20261119-123000.sql.gz', // きのう → 残る
            'memo.txt',                            // バックアップでない → さわらない
            'task-manager-old.sql.gz',             // 名前の形がちがう → さわらない
        ] as $name) {
            File::put($this->dir.'/'.$name, 'x');
        }

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertSame([
            'memo.txt',
            'task-manager-20261021-123000.sql.gz',
            'task-manager-20261119-123000.sql.gz',
            'task-manager-20261120-123000.sql.gz',
            'task-manager-old.sql.gz',
        ], $this->backups());
    }

    public function test_keep_days_can_be_changed(): void
    {
        config(['backup.keep_days' => 7]);
        File::ensureDirectoryExists($this->dir);
        $this->travelTo(Carbon::parse('2026-11-20 12:30:00'));
        File::put($this->dir.'/task-manager-20261112-123000.sql.gz', 'x'); // 8日前 → 消える
        File::put($this->dir.'/task-manager-20261114-123000.sql.gz', 'x'); // 6日前 → 残る

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertSame(['task-manager-20261114-123000.sql.gz', 'task-manager-20261120-123000.sql.gz'], $this->backups());
    }

    /**
     * 書き出しに失敗したら、失敗として終わり、こわれたファイルを残さない。古いバックアップも消さない
     */
    public function test_failed_backup_leaves_no_broken_file_and_keeps_old_ones(): void
    {
        File::ensureDirectoryExists($this->dir);
        $this->travelTo(Carbon::parse('2026-11-20 12:30:00'));
        File::put($this->dir.'/task-manager-20260101-123000.sql.gz', 'x'); // ずっと古い
        $connection = config('database.default');
        config(["database.connections.{$connection}.database" => 'no_such_database']);

        $this->artisan('backup:database')->assertFailed();

        $this->assertSame(['task-manager-20260101-123000.sql.gz'], $this->backups());
    }

    public function test_backup_is_scheduled_every_day(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        // 毎日 12:30（cron の書き方で「30 12 * * *」）に backup:database が入っている（D-045）
        $this->assertMatchesRegularExpression('/30 12 \* \* \*\s+php artisan backup:database/', $output);
    }

    public function test_restore_stops_when_the_file_is_missing(): void
    {
        $this->artisan('backup:restore', ['file' => 'task-manager-20990101-000000.sql.gz', '--force' => true])
            ->expectsOutputToContain('バックアップのファイルが見つかりません')
            ->assertFailed();
    }

    public function test_restore_asks_before_overwriting_and_can_be_cancelled(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/task-manager-20261120-123000.sql.gz', gzencode('SELECT 1;'));

        $this->artisan('backup:restore', ['file' => 'task-manager-20261120-123000.sql.gz'])
            ->expectsConfirmation($this->dir.'/task-manager-20261120-123000.sql.gz から戻します。今のデータは消えます。よいですか？', 'no')
            ->expectsOutput('戻すのをやめました')
            ->assertFailed();
    }
}
