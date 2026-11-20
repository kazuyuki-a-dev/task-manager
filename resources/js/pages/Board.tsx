import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import OrderModal from '../components/OrderModal';
import { deadlineText, formatDate } from '../lib/date';
import type { BoardColumn, Deadline, Member, Order, PageErrors, Product } from '../types';

type Props = {
    columns: BoardColumn[];
    /** 遅れと期限間近の指示。遅れが先、どちらも納期の近い順 */
    alerts: Order[];
    /** 'YYYY-MM-DD'。サーバーの「今日」 */
    today: string;
    /** 担当を足すプルダウンに出す社員（使えなくした人は入っていない） */
    members: Member[];
    /** 小さな画面で製品を選ぶための一覧 */
    products: Product[];
    /** 直せなかったときの理由（ほかの端末で先に変わっていたときなど） */
    errors: PageErrors;
};

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

const STATUS_LABELS: Record<Order['status'], string> = {
    not_started: '未着手',
    in_progress: '作業中',
    done: '完了',
};

const assigneeNames = (order: Order) => order.assignees.map((member) => member.name).join('、');

function DeadlineBadge({ order }: { order: Order }) {
    if (!order.deadline) return null;
    const style = DEADLINE_STYLES[order.deadline];
    return (
        <span className={`rounded-md px-2.5 py-1 text-base font-bold whitespace-nowrap ${style.badge}`}>
            {style.label}・{deadlineText(order.daysLeft)}
        </span>
    );
}

/** カードの担当は1行におさめる。何人もいるときは「山田 太郎 ほか2人」。全員の名前はミニ画面で見る */
function shortAssignees(order: Order): string {
    const [first, ...rest] = order.assignees;
    if (!first) return '未割り当て';
    return rest.length > 0 ? `${first.name} ほか${rest.length}人` : first.name;
}

/**
 * カードは見るだけ。押すと小さな画面が開く（D-028）。
 * どのカードも同じ高さになるように、行の数を決めておき、長い文字は1行で「…」にする
 */
function OrderCard({ order, onOpen }: { order: Order; onOpen: () => void }) {
    const highlight = order.deadline ? DEADLINE_STYLES[order.deadline].card : '';
    return (
        <li>
            <button
                type="button"
                onClick={onOpen}
                aria-label={`${order.productName}を開いて直す`}
                className={`w-full rounded-lg border border-gray-200 bg-white p-3 text-left shadow-sm hover:border-blue-400 hover:shadow ${highlight}`}
            >
                {/* 1行目：品番とダミーの印（左）、期限の印（右）。品番や印がなくても高さは同じ */}
                <div className="flex h-8 items-center justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="truncate text-xs text-gray-500">{order.productCode}</span>
                        {order.isDummy && (
                            <span className="shrink-0 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">
                                ダミー
                            </span>
                        )}
                    </div>
                    <DeadlineBadge order={order} />
                </div>
                {/* 2行目：品名。長いときは1行で「…」 */}
                <p className="truncate font-semibold text-gray-900">{order.productName}</p>
                <dl className="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                    <dt className="text-gray-500">数量</dt>
                    <dd className="text-gray-900">{order.quantity.toLocaleString()}</dd>
                    <dt className="text-gray-500">材料集結日</dt>
                    <dd className="text-gray-900">
                        {order.materialReadyDate ? formatDate(order.materialReadyDate) : '未定'}
                    </dd>
                    <dt className="text-gray-500">納期</dt>
                    <dd className="font-medium text-gray-900">{formatDate(order.dueDate)}</dd>
                    <dt className="text-gray-500">担当</dt>
                    <dd className={`truncate ${order.assignees.length > 0 ? 'text-gray-900' : 'text-red-700'}`}>
                        {shortAssignees(order)}
                    </dd>
                </dl>
            </button>
        </li>
    );
}

/** 画面のいちばん上の「遅れ・期限間近」のまとめ（R-02）。押すと小さな画面が開く */
function AlertSummary({ alerts, onOpen }: { alerts: Order[]; onOpen: (order: Order) => void }) {
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
        <section aria-labelledby="alerts-heading" className="mx-4 mt-4 overflow-hidden rounded-lg border border-red-200 bg-white">
            <h2 id="alerts-heading" className="flex flex-wrap gap-x-4 border-b border-red-200 bg-red-50 px-4 py-2">
                <span className="font-semibold text-red-800">遅れ {overdue}件</span>
                <span className="font-semibold text-amber-900">期限間近 {soon}件</span>
            </h2>
            <ul className="divide-y divide-gray-100">
                {alerts.map((order) => (
                    <li key={order.id}>
                        <button
                            type="button"
                            onClick={() => onOpen(order)}
                            className="flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-left text-sm hover:bg-gray-50"
                        >
                            <DeadlineBadge order={order} />
                            <span className="font-semibold text-gray-900">
                                {order.productCode && <span className="mr-1 font-normal text-gray-500">{order.productCode}</span>}
                                {order.productName}
                            </span>
                            <span className="text-gray-700">納期 {formatDate(order.dueDate)}</span>
                            <span className="text-gray-700">{STATUS_LABELS[order.status]}</span>
                            <span className={order.assignees.length > 0 ? 'text-gray-700' : 'text-red-700'}>
                                {order.assignees.length > 0 ? assigneeNames(order) : '未割り当て'}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Board({ columns, alerts, today, members, products, errors }: Props) {
    const [openOrderId, setOpenOrderId] = useState<number | null>(null);

    // 開いている指示は、毎回サーバーから届いた新しい内容で出す。
    // 完了にしてボードから消えたら、見つからないので小さな画面も閉じる
    const openOrder = columns.flatMap((column) => column.orders).find((order) => order.id === openOrderId);

    return (
        <>
            <Head title="進み具合のボード" />
            <div className="min-h-screen bg-gray-50">
                <header className="flex items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 py-3">
                    <div className="flex items-baseline gap-3">
                        <h1 className="text-xl font-semibold text-gray-900">進み具合のボード</h1>
                        <span className="text-sm text-gray-600">今日 {formatDate(today)}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* 完了した指示はボードに出さず、履歴の画面で見る（D-016） */}
                        <Link
                            href="/history"
                            className="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800"
                        >
                            完了の履歴
                        </Link>
                        {/* 管理者の画面へ。パスワードを入れていなければ、パスワードの画面が出る（R-06） */}
                        <Link
                            href="/admin"
                            className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                        >
                            管理者
                        </Link>
                    </div>
                </header>

                <AlertSummary alerts={alerts} onOpen={(order) => setOpenOrderId(order.id)} />

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
                                        <OrderCard key={order.id} order={order} onOpen={() => setOpenOrderId(order.id)} />
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </main>
            </div>

            {openOrder && (
                <OrderModal
                    // 別の指示を開いたら、入力欄を新しく作りなおす
                    key={openOrder.id}
                    order={openOrder}
                    members={members}
                    products={products}
                    errors={errors}
                    onClose={() => setOpenOrderId(null)}
                />
            )}
        </>
    );
}
