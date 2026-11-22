<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Models\Absence;
use App\Models\CalendarEvent;
use App\Models\CompanyHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * R-13 会社カレンダー（D-020・D-040）
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->withSession(['admin_last_activity' => now()->getTimestamp()]);
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private static function day(array $days, string $date): array
    {
        return collect($days)->firstWhere('date', $date);
    }

    /**
     * ログインしていなくても、だれでも見られる。日曜はじまりで、前後の月のはみ出す日もマスとして出す
     */
    public function test_anyone_can_see_a_month_starting_on_sunday(): void
    {
        // 社員がいても、管理者でなければ、休みを足すプルダウンの社員は送らない
        User::factory()->count(3)->create();
        $this->assertGuest();

        // 2026年12月1日は火曜。カレンダーは11/29(日)から、1/2(土)まで
        $this->get('/calendar?month=2026-12')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Calendar')
            ->where('month', '2026-12')
            ->where('prevMonth', '2026-11')
            ->where('nextMonth', '2027-01')
            ->has('days', 35)
            ->where('days.0.date', '2026-11-29')
            ->where('days.0.inMonth', false)
            ->where('days.2.date', '2026-12-01')
            ->where('days.2.inMonth', true)
            ->where('days.34.date', '2027-01-02')
            ->where('members', []));
    }

    public function test_month_defaults_to_this_month_and_ignores_bad_input(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));

        $this->get('/calendar')->assertInertia(fn (Assert $page) => $page->where('month', '2026-11')->where('today', '2026-11-20'));
        $this->get('/calendar?month=2026-13')->assertInertia(fn (Assert $page) => $page->where('month', '2026-11'));
        $this->get('/calendar?month=abc')->assertInertia(fn (Assert $page) => $page->where('month', '2026-11'));
    }

    /**
     * マスには、休みのメンバーの名前と行事のタイトルが入る。作業者には有給か欠勤かを送らない（D-020）
     */
    public function test_days_show_absences_and_events_without_type_for_workers(): void
    {
        $worker = User::factory()->create(['name' => '山田 太郎']);
        Absence::create(['date' => '2026-12-03', 'user_id' => $worker->id, 'type' => AbsenceType::Paid]);
        CalendarEvent::create(['date' => '2026-12-03', 'title' => '棚卸', 'body' => '第2倉庫']);

        $this->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', function ($days) use ($worker) {
                $day = self::day($days->all(), '2026-12-03');
                // 有給か欠勤かは、画面で隠すだけでなく、送りもしない
                return $day['absences'] === [['id' => Absence::first()->id, 'userId' => $worker->id, 'name' => '山田 太郎']]
                    && $day['events'] === [['id' => CalendarEvent::first()->id, 'title' => '棚卸', 'body' => '第2倉庫']];
            }));
    }

    /**
     * 管理者には、有給か欠勤かと、休みを足すプルダウンの社員を送る
     */
    public function test_admin_sees_absence_type_and_member_options(): void
    {
        $worker = User::factory()->create(['name' => '佐藤 花子']);
        User::factory()->inactive()->create(['name' => '消した人']);
        Absence::create(['date' => '2026-12-04', 'user_id' => $worker->id, 'type' => AbsenceType::Absent]);

        $this->asAdmin()->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', fn ($days) => self::day($days->all(), '2026-12-04')['absences'][0]['typeLabel'] === '欠勤'
                && self::day($days->all(), '2026-12-04')['absences'][0]['type'] === 'absent')
            ->where('members', fn ($members) => collect($members)->pluck('name')->contains('佐藤 花子')
                && ! collect($members)->pluck('name')->contains('消した人')));
    }

    /**
     * ほかの月の休みや行事は、マスに出ない。
     * あわせて、データベースには「カレンダーのはんい（11/29〜1/2）」でしぼって聞いていることも確かめる
     * （しぼらないと、年月がたつほど読む量が増えて重くなるため）
     */
    public function test_only_the_month_range_is_loaded(): void
    {
        $worker = User::factory()->create();
        Absence::create(['date' => '2026-10-15', 'user_id' => $worker->id, 'type' => AbsenceType::Paid]);
        CalendarEvent::create(['date' => '2027-02-01', 'title' => 'ずっと先']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', fn ($days) => collect($days)->every(fn ($d) => $d['absences'] === [] && $d['events'] === [])));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (['absences', 'calendar_events', 'company_holidays'] as $table) {
            // 表の名前のかこみ方は、MySQL は `、SQLite は " なので、どちらでも見つける
            $query = $queries->first(fn ($q) => preg_match('/from [`"]'.$table.'[`"]/', $q['query']) === 1);
            $this->assertNotNull($query, "{$table} を読んでいない");
            $this->assertStringContainsString('between', $query['query']);
            $this->assertSame(['2026-11-29', '2027-01-02'], $query['bindings']);
        }
    }

    public function test_adding_and_removing_requires_the_admin_password(): void
    {
        $worker = User::factory()->create();
        $absence = Absence::create(['date' => '2026-12-03', 'user_id' => $worker->id, 'type' => AbsenceType::Paid]);
        $event = CalendarEvent::create(['date' => '2026-12-03', 'title' => '棚卸']);

        $this->post('/admin/absences', ['date' => '2026-12-04', 'user_id' => $worker->id, 'type' => 'paid'])->assertRedirect('/admin/login');
        $this->delete("/admin/absences/{$absence->id}")->assertRedirect('/admin/login');
        $this->post('/admin/calendar-events', ['date' => '2026-12-04', 'title' => '行事'])->assertRedirect('/admin/login');
        $this->delete("/admin/calendar-events/{$event->id}")->assertRedirect('/admin/login');

        $this->assertSame(1, Absence::count());
        $this->assertSame(1, CalendarEvent::count());
    }

    public function test_admin_can_add_and_remove_an_absence(): void
    {
        $worker = User::factory()->create();

        $this->asAdmin()->from('/calendar?month=2026-12')
            ->post('/admin/absences', ['date' => '2026-12-03', 'user_id' => $worker->id, 'type' => 'absent'])
            ->assertRedirect('/calendar?month=2026-12')
            ->assertSessionHasNoErrors();

        $absence = Absence::sole();
        $this->assertSame('2026-12-03', $absence->date->toDateString());
        $this->assertSame(AbsenceType::Absent, $absence->type);

        $this->delete("/admin/absences/{$absence->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($absence);
    }

    public function test_absence_input_is_checked(): void
    {
        $worker = User::factory()->create();
        $gone = User::factory()->inactive()->create();
        Absence::create(['date' => '2026-12-03', 'user_id' => $worker->id, 'type' => AbsenceType::Paid]);
        $this->asAdmin()->from('/calendar');

        // 同じ人を同じ日に二重に入れない（ほかの日ならよい）
        $this->post('/admin/absences', ['date' => '2026-12-03', 'user_id' => $worker->id, 'type' => 'absent'])
            ->assertSessionHasErrors(['user_id' => 'その人は、この日はもう休みに入っています。']);
        $this->post('/admin/absences', ['date' => '2026-12-04', 'user_id' => $worker->id, 'type' => 'paid'])->assertSessionHasNoErrors();
        // 消した社員は入れない
        $this->post('/admin/absences', ['date' => '2026-12-05', 'user_id' => $gone->id, 'type' => 'paid'])
            ->assertSessionHasErrors(['user_id' => '選んだメンバーは、休みに入れられません。']);
        $this->post('/admin/absences', ['date' => '2026-12-05', 'user_id' => '', 'type' => 'paid'])
            ->assertSessionHasErrors(['user_id' => 'メンバーを選んでください。']);
        $this->post('/admin/absences', ['date' => '2026-12-05', 'user_id' => $worker->id, 'type' => 'holiday'])
            ->assertSessionHasErrors('type');
        $this->post('/admin/absences', ['date' => '12/05', 'user_id' => $worker->id, 'type' => 'paid'])
            ->assertSessionHasErrors('date');

        $this->assertSame(2, Absence::count());
    }

    public function test_admin_can_add_and_remove_an_event(): void
    {
        $this->asAdmin()->from('/calendar')
            ->post('/admin/calendar-events', ['date' => '2026-12-29', 'title' => '年末年始休み', 'body' => ''])
            ->assertSessionHasNoErrors();

        $event = CalendarEvent::sole();
        $this->assertSame('年末年始休み', $event->title);
        // 空の内容は null になる
        $this->assertNull($event->body);

        $this->delete("/admin/calendar-events/{$event->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($event);
    }

    public function test_event_input_is_checked(): void
    {
        $this->asAdmin()->from('/calendar');

        $this->post('/admin/calendar-events', ['date' => '2026-12-29', 'title' => ''])
            ->assertSessionHasErrors(['title' => '行事のタイトルを入れてください。']);
        $this->post('/admin/calendar-events', ['date' => '2026-12-29', 'title' => str_repeat('あ', 51)])
            ->assertSessionHasErrors(['title' => 'タイトルは50文字までにしてください。']);
        $this->post('/admin/calendar-events', ['date' => '2026-12-29', 'title' => '棚卸', 'body' => str_repeat('あ', 201)])
            ->assertSessionHasErrors(['body' => '内容は200文字までにしてください。']);
        // 200文字ちょうどはよい
        $this->post('/admin/calendar-events', ['date' => '2026-12-29', 'title' => '棚卸', 'body' => str_repeat('あ', 200)])->assertSessionHasNoErrors();

        $this->assertSame(1, CalendarEvent::count());
    }

    /**
     * 会社の休みの日は、だれが見ても分かる（マスの色を変えるための印）（D-042）
     */
    public function test_company_holidays_are_marked_for_everyone(): void
    {
        CompanyHoliday::create(['date' => '2026-12-29']);
        CompanyHoliday::create(['date' => '2026-12-30']);

        $this->assertGuest();
        $this->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', fn ($days) => self::day($days->all(), '2026-12-29')['isHoliday'] === true
                && self::day($days->all(), '2026-12-30')['isHoliday'] === true
                && self::day($days->all(), '2026-12-28')['isHoliday'] === false));
    }

    /**
     * 日曜日は、何も登録しなくても会社の休み。土曜日はちがう（D-043）
     */
    public function test_sundays_are_company_holidays_by_default(): void
    {
        $this->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', fn ($days) => self::day($days->all(), '2026-12-06')['isHoliday'] === true   // 日曜
                && self::day($days->all(), '2026-12-13')['isHoliday'] === true                         // 日曜
                && self::day($days->all(), '2026-12-05')['isHoliday'] === false                        // 土曜
                && self::day($days->all(), '2026-12-07')['isHoliday'] === false));                     // 月曜
        $this->assertSame(0, CompanyHoliday::count());
    }

    /**
     * 日曜日も「会社の休みをやめる」で出勤日にできる（休日出勤）。もどせば、決まりとちがう日の登録は消える
     */
    public function test_a_sunday_can_be_made_a_working_day_and_back(): void
    {
        $this->asAdmin()->from('/calendar?month=2026-12');

        $this->put('/admin/company-holidays', ['date' => '2026-12-06', 'holiday' => false])->assertSessionHasNoErrors();
        $this->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', fn ($days) => self::day($days->all(), '2026-12-06')['isHoliday'] === false));
        $this->assertSame(1, CompanyHoliday::count());

        $this->put('/admin/company-holidays', ['date' => '2026-12-06', 'holiday' => true])->assertSessionHasNoErrors();
        $this->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', fn ($days) => self::day($days->all(), '2026-12-06')['isHoliday'] === true));
        // 日曜日を休みにもどすのは決まりと同じなので、登録は残さない
        $this->assertSame(0, CompanyHoliday::count());
    }

    /**
     * 日曜日を「休みにする」と送っても、もともと休みなので、何も登録しない
     */
    public function test_marking_a_sunday_as_holiday_stores_nothing(): void
    {
        $this->asAdmin()->from('/calendar');

        $this->put('/admin/company-holidays', ['date' => '2026-12-06', 'holiday' => true])->assertSessionHasNoErrors();

        $this->assertSame(0, CompanyHoliday::count());
    }

    public function test_admin_can_turn_a_company_holiday_on_and_off(): void
    {
        $this->asAdmin()->from('/calendar?month=2026-12');

        $this->put('/admin/company-holidays', ['date' => '2026-12-29', 'holiday' => true])
            ->assertRedirect('/calendar?month=2026-12')->assertSessionHasNoErrors();
        $this->assertTrue(CompanyHoliday::whereDate('date', '2026-12-29')->exists());

        $this->put('/admin/company-holidays', ['date' => '2026-12-29', 'holiday' => false])->assertSessionHasNoErrors();
        $this->assertFalse(CompanyHoliday::whereDate('date', '2026-12-29')->exists());
    }

    /**
     * 2回続けて押されても（2台でほぼ同時に押されても）、思ったのと逆にならない
     */
    public function test_setting_a_company_holiday_twice_keeps_the_same_result(): void
    {
        $this->asAdmin()->from('/calendar');

        $this->put('/admin/company-holidays', ['date' => '2026-12-29', 'holiday' => true]);
        $this->put('/admin/company-holidays', ['date' => '2026-12-29', 'holiday' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, CompanyHoliday::count());

        $this->put('/admin/company-holidays', ['date' => '2026-12-29', 'holiday' => false]);
        $this->put('/admin/company-holidays', ['date' => '2026-12-29', 'holiday' => false])->assertSessionHasNoErrors();
        $this->assertSame(0, CompanyHoliday::count());
    }

    public function test_company_holiday_requires_the_admin_password_and_valid_input(): void
    {
        $this->put('/admin/company-holidays', ['date' => '2026-12-29', 'holiday' => true])->assertRedirect('/admin/login');
        $this->assertSame(0, CompanyHoliday::count());

        $this->asAdmin()->from('/calendar');
        $this->put('/admin/company-holidays', ['date' => '12/29', 'holiday' => true])->assertSessionHasErrors('date');
        $this->put('/admin/company-holidays', ['date' => '2026-12-29'])->assertSessionHasErrors('holiday');
        $this->assertSame(0, CompanyHoliday::count());
    }

    /**
     * 消した社員の休みも、カレンダーには名前が残る（D-036）
     */
    public function test_deleted_members_absences_still_show_their_names(): void
    {
        $worker = User::factory()->create(['name' => '辞めた人']);
        Absence::create(['date' => '2026-12-03', 'user_id' => $worker->id, 'type' => AbsenceType::Paid]);
        $worker->update(['is_active' => false]);

        $this->get('/calendar?month=2026-12')->assertInertia(fn (Assert $page) => $page
            ->where('days', fn ($days) => self::day($days->all(), '2026-12-03')['absences'][0]['name'] === '辞めた人'));
    }

    /**
     * 休みと行事が増えても、問い合わせの回数が増えない（社員の名前を1件ずつ読まない）
     */
    public function test_query_count_does_not_grow(): void
    {
        $workers = User::factory()->count(20)->create();
        $add = function (int $days) use ($workers) {
            static $next = 1;
            for ($i = 0; $i < $days; $i++, $next++) {
                $date = sprintf('2026-12-%02d', $next);
                foreach ($workers->random(3) as $worker) {
                    Absence::create(['date' => $date, 'user_id' => $worker->id, 'type' => AbsenceType::Paid]);
                }
                CalendarEvent::create(['date' => $date, 'title' => "行事{$next}"]);
            }
        };

        $add(2);
        $few = $this->countQueries(fn () => $this->get('/calendar?month=2026-12')->assertOk());
        $add(20);
        $many = $this->countQueries(fn () => $this->get('/calendar?month=2026-12')->assertOk());

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
