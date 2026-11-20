import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Modal from '../components/Modal';
import { formatDate } from '../lib/date';
import type { Member, SharedProps } from '../types';

type AbsenceItem = {
    id: number;
    userId: number;
    name: string;
    /** 有給か欠勤か。管理者のときだけ送られてくる（D-020） */
    type?: 'paid' | 'absent';
    typeLabel?: string;
};

type EventItem = {
    id: number;
    title: string;
    body: string | null;
};

type Day = {
    /** 'YYYY-MM-DD' */
    date: string;
    /** この月の日か（前後の月のはみ出す日は false） */
    inMonth: boolean;
    absences: AbsenceItem[];
    events: EventItem[];
};

type Props = {
    /** 'YYYY-MM' */
    month: string;
    prevMonth: string;
    nextMonth: string;
    today: string;
    days: Day[];
    /** 休みを足すプルダウンに出す社員（管理者のときだけ） */
    members: Member[];
};

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

/** 会社カレンダー（R-13・D-040）。マスには休みのメンバーと行事のタイトルだけ。押すと大きく出す */
export default function Calendar({ month, prevMonth, nextMonth, today, days, members }: Props) {
    const { admin } = usePage<SharedProps>().props;
    const [openDate, setOpenDate] = useState<string | null>(null);
    // 開いている日は、毎回サーバーから届いた新しい内容で出す
    const openDay = days.find((day) => day.date === openDate);
    const [year, monthNumber] = month.split('-').map(Number);

    const go = (to: string) => router.get('/calendar', { month: to }, { preserveScroll: true });

    return (
        <>
            <Head title="会社カレンダー" />
            <div className="min-h-screen bg-gray-50">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3">
                    <h1 className="text-xl font-semibold text-gray-900">会社カレンダー</h1>
                    <Link href="/" className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                        ボードに戻る
                    </Link>
                </header>

                <main className="mx-auto max-w-6xl space-y-3 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <button type="button" onClick={() => go(prevMonth)} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                            ＜ 前の月
                        </button>
                        <h2 className="text-2xl font-bold text-gray-900">
                            {year}年{monthNumber}月
                        </h2>
                        <button type="button" onClick={() => go(nextMonth)} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                            次の月 ＞
                        </button>
                    </div>

                    {/* 色の見かた。色だけでなく、行事には「■」を付けて見分けられるようにする */}
                    <p className="flex gap-4 text-sm text-gray-600">
                        <span className="rounded bg-blue-100 px-1.5 text-blue-900">■ 行事</span>
                        <span className="rounded bg-orange-50 px-1.5 text-orange-800">休みのメンバー</span>
                        {admin && <span>日付を押すと、休みと行事を足せます</span>}
                    </p>

                    <div className="grid grid-cols-7 overflow-hidden rounded-lg border border-gray-200 bg-white">
                        {WEEKDAYS.map((weekday, index) => (
                            <div
                                key={weekday}
                                className={`border-b border-gray-200 py-1 text-center text-sm font-semibold ${index === 0 ? 'text-red-700' : index === 6 ? 'text-blue-700' : 'text-gray-700'}`}
                            >
                                {weekday}
                            </div>
                        ))}
                        {days.map((day, index) => (
                            <DayCell key={day.date} day={day} isToday={day.date === today} weekday={index % 7} onOpen={() => setOpenDate(day.date)} />
                        ))}
                    </div>
                </main>
            </div>

            {openDay && <DayModal key={openDay.date} day={openDay} members={members} isAdmin={admin !== null} onClose={() => setOpenDate(null)} />}
        </>
    );
}

/** 日付のマス。休みのメンバーの名前と、行事のタイトルだけを出す */
function DayCell({ day, isToday, weekday, onOpen }: { day: Day; isToday: boolean; weekday: number; onOpen: () => void }) {
    const dayNumber = Number(day.date.slice(8, 10));
    const numberColor = !day.inMonth ? 'text-gray-400' : weekday === 0 ? 'text-red-700' : weekday === 6 ? 'text-blue-700' : 'text-gray-900';

    return (
        <button
            type="button"
            onClick={onOpen}
            aria-label={`${formatDate(day.date)}を開く`}
            className={`min-h-24 border-b border-r border-gray-100 p-1 text-left align-top hover:bg-blue-50 ${day.inMonth ? '' : 'bg-gray-50'}`}
        >
            <span className={`inline-flex h-6 min-w-6 items-center justify-center rounded-full text-sm font-semibold ${numberColor} ${isToday ? 'bg-gray-900 text-white' : ''}`}>
                {dayNumber}
            </span>
            <span className="mt-0.5 block space-y-0.5">
                {day.events.map((event) => (
                    <span key={event.id} className="block truncate rounded bg-blue-100 px-1 text-xs font-medium text-blue-900">
                        ■ {event.title}
                    </span>
                ))}
                {day.absences.map((absence) => (
                    <span key={absence.id} className="block truncate rounded bg-orange-50 px-1 text-xs text-orange-800">
                        {absence.name}
                    </span>
                ))}
            </span>
        </button>
    );
}

const inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5';

function FieldError({ message }: { message?: string }) {
    return message ? <span className="mt-1 block text-sm text-red-700">{message}</span> : null;
}

function SectionTitle({ children }: { children: React.ReactNode }) {
    return <h3 className="mb-2 text-sm font-semibold text-gray-500">{children}</h3>;
}

/** 日付のマスを押すと開く小さな画面。その日の休みと行事を大きく出す。管理者のときだけ、足す・取り消すができる */
function DayModal({ day, members, isAdmin, onClose }: { day: Day; members: Member[]; isAdmin: boolean; onClose: () => void }) {
    const remove = (url: string, label: string) => {
        if (!window.confirm(`${label}を取り消します。よいですか？`)) return;
        router.delete(url, { preserveScroll: true, preserveState: true });
    };

    return (
        <Modal title={formatDate(day.date)} onClose={onClose}>
            <section>
                <SectionTitle>休み</SectionTitle>
                {day.absences.length === 0 ? (
                    <p className="text-gray-500">休みの人はいません</p>
                ) : (
                    <ul className="space-y-1">
                        {day.absences.map((absence) => (
                            <li key={absence.id} className="flex items-center justify-between gap-2 rounded-lg bg-orange-50 px-3 py-2">
                                <span className="text-lg font-medium text-orange-900">
                                    {absence.name}
                                    {/* 有給か欠勤かは、管理者のときだけ（D-020） */}
                                    {absence.typeLabel && <span className="ml-2 text-sm text-orange-700">（{absence.typeLabel}）</span>}
                                </span>
                                {isAdmin && (
                                    <button
                                        type="button"
                                        onClick={() => remove(`/admin/absences/${absence.id}`, `${absence.name}さんの休み`)}
                                        className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                    >
                                        取り消す
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {isAdmin && <AbsenceForm day={day} members={members} />}
            </section>

            <section>
                <SectionTitle>行事</SectionTitle>
                {day.events.length === 0 ? (
                    <p className="text-gray-500">行事はありません</p>
                ) : (
                    <ul className="space-y-1">
                        {day.events.map((event) => (
                            <li key={event.id} className="flex items-start justify-between gap-2 rounded-lg bg-blue-50 px-3 py-2">
                                <span className="min-w-0">
                                    <span className="block text-lg font-semibold text-blue-900">■ {event.title}</span>
                                    {event.body && <span className="block whitespace-pre-wrap text-gray-700">{event.body}</span>}
                                </span>
                                {isAdmin && (
                                    <button
                                        type="button"
                                        onClick={() => remove(`/admin/calendar-events/${event.id}`, `行事「${event.title}」`)}
                                        className="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                    >
                                        取り消す
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {isAdmin && <EventForm day={day} />}
            </section>
        </Modal>
    );
}

/** 休みを足す。メンバーをプルダウンで選び、有給か欠勤かを選ぶ */
function AbsenceForm({ day, members }: { day: Day; members: Member[] }) {
    const form = useForm({ date: day.date, user_id: '', type: 'paid' });
    // この日にもう休みに入っている人は、プルダウンに出さない（同じ名前の人がいても取りちがえないように、番号で見分ける）
    const already = new Set(day.absences.map((absence) => absence.userId));
    const candidates = members.filter((member) => !already.has(member.id));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/absences', { preserveScroll: true, preserveState: true, onSuccess: () => form.reset('user_id') });
    };

    return (
        <form onSubmit={submit} className="mt-3 grid gap-2 sm:grid-cols-[1fr_7rem_auto] sm:items-start">
            <label className="block">
                <span className="sr-only">メンバー</span>
                <select value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} className={inputClass}>
                    <option value="">メンバーを選ぶ</option>
                    {candidates.map((member) => (
                        <option key={member.id} value={member.id}>
                            {member.name}
                        </option>
                    ))}
                </select>
                <FieldError message={form.errors.user_id} />
            </label>
            <label className="block">
                <span className="sr-only">有給か欠勤か</span>
                <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className={inputClass}>
                    <option value="paid">有給</option>
                    <option value="absent">欠勤</option>
                </select>
                <FieldError message={form.errors.type} />
            </label>
            <button type="submit" disabled={form.processing} className="rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-orange-700 disabled:opacity-50">
                休みを足す
            </button>
        </form>
    );
}

/** 行事を足す。タイトルは必ず、内容は少しだけ */
function EventForm({ day }: { day: Day }) {
    const form = useForm({ date: day.date, title: '', body: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/calendar-events', { preserveScroll: true, preserveState: true, onSuccess: () => form.reset('title', 'body') });
    };

    return (
        <form onSubmit={submit} className="mt-3 space-y-2">
            <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">タイトル</span>
                <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} maxLength={50} placeholder="例：棚卸、健康診断、年末年始休み" className={inputClass} />
                <FieldError message={form.errors.title} />
            </label>
            <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">内容（少しだけ。空でもよい）</span>
                <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} maxLength={200} rows={2} className={inputClass} />
                <span className="block text-right text-xs text-gray-500">{form.data.body.length} / 200</span>
                <FieldError message={form.errors.body} />
            </label>
            <div className="flex justify-end">
                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    行事を足す
                </button>
            </div>
        </form>
    );
}
