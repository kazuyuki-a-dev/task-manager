<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AbsenceType;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\CalendarEvent;
use App\Models\CompanyHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 会社カレンダーに、休みと行事を足す・取り消す（R-13・D-020・D-040）。管理者だけが使う
 */
class CalendarController extends Controller
{
    /**
     * 会社の休みにする・やめる（D-042）。送られてきた「休みにするか」のとおりにする
     * （押すたびに入れかえる形にしないのは、2回続けて押されても、思ったのと逆にならないようにするため）
     */
    public function setHoliday(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'holiday' => ['required', 'boolean'],
        ], [
            'date.required' => '日付がありません。',
            'date.date_format' => '日付の形がちがいます。',
        ]);

        if ($validated['holiday']) {
            CompanyHoliday::firstOrCreate(['date' => $validated['date']]);
        } else {
            CompanyHoliday::where('date', $validated['date'])->delete();
        }

        return back();
    }

    public function storeAbsence(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            // 消した社員は選べない
            'user_id' => [
                'required', 'integer', 'exists:users,id,is_active,1',
                // 同じ人を同じ日に二重に入れない
                Rule::unique('absences', 'user_id')->where('date', $request->input('date')),
            ],
            'type' => ['required', Rule::enum(AbsenceType::class)],
        ], [
            'date.required' => '日付がありません。',
            'date.date_format' => '日付の形がちがいます。',
            'user_id.required' => 'メンバーを選んでください。',
            'user_id.exists' => '選んだメンバーは、休みに入れられません。',
            'user_id.unique' => 'その人は、この日はもう休みに入っています。',
            'type.required' => '有給か欠勤かを選んでください。',
            'type.enum' => '有給か欠勤かを選んでください。',
        ]);

        Absence::create($validated);

        return back();
    }

    public function destroyAbsence(Absence $absence): RedirectResponse
    {
        $absence->delete();

        return back();
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'max:50'],
            'body' => ['nullable', 'string', 'max:200'],
        ], [
            'date.required' => '日付がありません。',
            'date.date_format' => '日付の形がちがいます。',
            'title.required' => '行事のタイトルを入れてください。',
            'title.max' => 'タイトルは50文字までにしてください。',
            'body.max' => '内容は200文字までにしてください。',
        ]);

        CalendarEvent::create($validated);

        return back();
    }

    public function destroyEvent(CalendarEvent $event): RedirectResponse
    {
        $event->delete();

        return back();
    }
}
