import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { BoardColumn, Deadline, Order, OrderStatus } from '../types';

type Props = {
    columns: BoardColumn[];
    /** 遅れと期限間近の指示。遅れが先、どちらも納期の近い順 */
    alerts: Order[];
    /** 'YYYY-MM-DD'。サーバーの「今日」 */
    today: string;
    /** 状態を変えられなかったときの理由（ほかの端末で先に変わっていたときなど） */
    errors: { status?: string };
};

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

/**
 * 'YYYY-MM-DD' を「11/22(日)」の形にする。
 * new Date('YYYY-MM-DD') は世界標準時として読まれ、日付がずれることがあるので、数字に分けて作る
 */
function formatDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return `${month}/${day}(${WEEKDAYS[date.getDay()]})`;
}

/** 「3日遅れ」「今日まで」「あと2日」 */
function deadlineText(daysLeft: number): string {
    if (daysLeft < 0) return `${-daysLeft}日遅れ`;
    if (daysLeft === 0) return '今日まで';
    return `あと${daysLeft}日`;
}

// 色だけで見分けなくてよいように、どの場合も文字（遅れ・期限間近）も出す
const DEADLINE_STYLES: Record<Deadline, { card: string; badge: string; label: string }> = {
    overdue: {
        card: 'border-l-4 border-l-red-600',
        badge: 'bg-red-600 text-white',
        label: '遅れ',
    },
    soon: {
        card: 'border-l-4 border-l-amber-500',
        badge: 'bg-amber-100 text-amber-900',
        label: '期限間近',
    },
};

// 列ごとの見出しの線の色
const COLUMN_STYLES: Record<BoardColumn['status'], string> = {
    not_started: 'border-gray-400',
    in_progress: 'border-blue-500',
    done: 'border-green-600',
};

function DeadlineBadge({ order }: { order: Order }) {
    if (!order.deadline) return null;
    const style = DEADLINE_STYLES[order.deadline];
    return (
        <span className={`rounded-md px-2.5 py-1 text-base font-bold whitespace-nowrap ${style.badge}`}>
            {style.label}・{deadlineText(order.daysLeft)}
        </span>
    );
}

function ProductName({ order }: { order: Order }) {
    return (
        <div>
            {order.productCode && <p className="text-xs text-gray-500">{order.productCode}</p>}
            <p className="font-semibold text-gray-900">{order.productName}</p>
        </div>
    );
}

// カードに出すボタン。完了 → 作業中に戻すボタンは、完了した指示が出る履歴の画面に置く（D-024）
const NEXT_ACTIONS: Partial<Record<OrderStatus, { next: OrderStatus; label: string; style: string }>> = {
    not_started: { next: 'in_progress', label: '作業を始める', style: 'bg-blue-600 hover:bg-blue-700' },
    in_progress: { next: 'done', label: '完了にする', style: 'bg-green-700 hover:bg-green-800' },
};

function StatusButton({ order }: { order: Order }) {
    const [processing, setProcessing] = useState(false);
    const action = NEXT_ACTIONS[order.status];
    if (!action) return null;

    const change = () => {
        router.patch(
            `/orders/${order.id}/status`,
            { status: action.next },
            {
                // 押したあとも、画面の同じ位置のままにする
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    // カードの右下に置く。指で押しやすい高さ（約44px）は残す
    return (
        <div className="mt-2 flex justify-end">
            <button
                type="button"
                onClick={change}
                disabled={processing}
                className={`rounded-lg px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50 ${action.style}`}
            >
                {processing ? '変えています…' : action.label}
            </button>
        </div>
    );
}

function OrderCard({ order }: { order: Order }) {
    const highlight = order.deadline ? DEADLINE_STYLES[order.deadline].card : '';
    return (
        <li className={`rounded-lg border border-gray-200 bg-white p-3 shadow-sm ${highlight}`}>
            <div className="flex items-start justify-between gap-2">
                <ProductName order={order} />
                <div className="flex shrink-0 flex-col items-end gap-1">
                    <DeadlineBadge order={order} />
                    {order.isDummy && (
                        <span className="rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">
                            ダミー
                        </span>
                    )}
                </div>
            </div>
            <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt className="text-gray-500">数量</dt>
                <dd className="text-gray-900">{order.quantity.toLocaleString()}</dd>
                <dt className="text-gray-500">材料集結日</dt>
                <dd className="text-gray-900">
                    {order.materialReadyDate ? formatDate(order.materialReadyDate) : '未定'}
                </dd>
                <dt className="text-gray-500">納期</dt>
                <dd className="font-medium text-gray-900">{formatDate(order.dueDate)}</dd>
                <dt className="text-gray-500">担当</dt>
                <dd className={order.assignees.length > 0 ? 'text-gray-900' : 'text-red-700'}>
                    {order.assignees.length > 0 ? order.assignees.join('、') : '未割り当て'}
                </dd>
            </dl>
            <StatusButton order={order} />
        </li>
    );
}

const STATUS_LABELS: Record<Order['status'], string> = {
    not_started: '未着手',
    in_progress: '作業中',
    done: '完了',
};

/** 画面のいちばん上の「遅れ・期限間近」のまとめ（R-02） */
function AlertSummary({ alerts }: { alerts: Order[] }) {
    if (alerts.length === 0) {
        return (
            <section className="mx-4 mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                遅れ・期限間近の指示はありません
            </section>
        );
    }

    const overdue = alerts.filter((order) => order.deadline === 'overdue').length;
    const soon = alerts.length - overdue;

    return (
        <section aria-labelledby="alerts-heading" className="mx-4 mt-4 rounded-lg border border-red-200 bg-white">
            <h2 id="alerts-heading" className="flex flex-wrap gap-x-4 border-b border-red-200 bg-red-50 px-4 py-2">
                <span className="font-semibold text-red-800">遅れ {overdue}件</span>
                <span className="font-semibold text-amber-900">期限間近 {soon}件</span>
            </h2>
            <ul className="divide-y divide-gray-100">
                {alerts.map((order) => (
                    <li key={order.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-sm">
                        <DeadlineBadge order={order} />
                        <span className="font-semibold text-gray-900">
                            {order.productCode && <span className="mr-1 font-normal text-gray-500">{order.productCode}</span>}
                            {order.productName}
                        </span>
                        <span className="text-gray-700">納期 {formatDate(order.dueDate)}</span>
                        <span className="text-gray-700">{STATUS_LABELS[order.status]}</span>
                        <span className={order.assignees.length > 0 ? 'text-gray-700' : 'text-red-700'}>
                            {order.assignees.length > 0 ? order.assignees.join('、') : '未割り当て'}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Board({ columns, alerts, today, errors }: Props) {
    return (
        <>
            <Head title="進み具合のボード" />
            <div className="min-h-screen bg-gray-50">
                <header className="flex items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 py-3">
                    <div className="flex items-baseline gap-3">
                        <h1 className="text-xl font-semibold text-gray-900">進み具合のボード</h1>
                        <span className="text-sm text-gray-600">今日 {formatDate(today)}</span>
                    </div>
                    {/* 完了した指示はボードに出さず、履歴の画面で見る（D-016） */}
                    <Link
                        href="/history"
                        className="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800"
                    >
                        完了の履歴
                    </Link>
                </header>

                {errors.status && (
                    <p role="alert" className="mx-4 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 font-medium text-red-800">
                        {errors.status}
                    </p>
                )}

                <AlertSummary alerts={alerts} />

                <main className="grid gap-4 p-4 md:grid-cols-2">
                    {columns.map((column) => (
                        <section key={column.status} aria-labelledby={`column-${column.status}`}>
                            <h2
                                id={`column-${column.status}`}
                                className={`mb-3 flex items-baseline justify-between border-b-4 pb-1 ${COLUMN_STYLES[column.status]}`}
                            >
                                <span className="text-lg font-semibold text-gray-900">{column.label}</span>
                                <span className="text-sm text-gray-600">{column.orders.length}件</span>
                            </h2>

                            {column.orders.length === 0 ? (
                                <p className="text-sm text-gray-500">ありません</p>
                            ) : (
                                <ul className="space-y-2">
                                    {column.orders.map((order) => (
                                        <OrderCard key={order.id} order={order} />
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </main>
            </div>
        </>
    );
}
