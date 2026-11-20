<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminSession;
use App\Models\Absence;
use App\Models\CalendarEvent;
use App\Models\CompanyHoliday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 会社カレンダー（R-13・D-020・D-040）。だれでも見られる。
 * 休みと行事を足す・取り消すのは管理者だけ（Admin\CalendarController）
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $month = $this->month((string) $request->query('month', ''));
        $isAdmin = EnsureAdminSession::isActive($request);

        // カレンダーは日曜はじまり。月の前後の週のはみ出す日も、マスとして出す
        $start = $month->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $absences = Absence::query()
            ->with('user:id,name')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Absence $absence) => $absence->date->toDateString());

        $events = CalendarEvent::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CalendarEvent $event) => $event->date->toDateString());

        // 会社の休みの決まりとちがう日（D-042・D-043）。日付 => 休みかどうか
        $overrides = CompanyHoliday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'is_holiday'])
            ->mapWithKeys(fn (CompanyHoliday $holiday) => [$holiday->date->toDateString() => $holiday->is_holiday]);

        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'inMonth' => $day->month === $month->month,
                // 決まりとちがう日として登録があればそのとおり、なければ決まり（日曜日は休み）
                'isHoliday' => $overrides[$key] ?? CompanyHoliday::isHolidayByDefault($day),
                'absences' => ($absences[$key] ?? collect())->map(fn (Absence $absence) => [
                    'id' => $absence->id,
                    'userId' => $absence->user_id,
                    'name' => $absence->user->name,
                    // 有給か欠勤かは、管理者にだけ送る。画面で隠すだけでなく、送りもしない（D-020）
                    ...($isAdmin ? ['type' => $absence->type->value, 'typeLabel' => $absence->type->label()] : []),
                ])->values()->all(),
                'events' => ($events[$key] ?? collect())->map(fn (CalendarEvent $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'body' => $event->body,
                ])->values()->all(),
            ];
        }

        return Inertia::render('Calendar', [
            'month' => $month->format('Y-m'),
            'prevMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
            'today' => today()->toDateString(),
            'days' => $days,
            // 休みを足すプルダウンに出す社員。管理者のときだけ送る
            'members' => $isAdmin
                ? User::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->all()
                : [],
        ]);
    }

    /**
     * 「2026-12」の形を読む。読めないときは今月
     */
    private function month(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
