import { Head } from '@inertiajs/react';
import type { BoardColumn, Order } from '../types';

type Props = {
    columns: BoardColumn[];
    doneTotal: number;
    doneLimit: number;
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

// 列ごとの見出しの色
const COLUMN_STYLES: Record<BoardColumn['status'], string> = {
    not_started: 'border-gray-400',
    in_progress: 'border-blue-500',
    done: 'border-green-600',
};

function OrderCard({ order }: { order: Order }) {
    return (
        <li className="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
            <div className="flex items-start justify-between gap-2">
                <p className="font-semibold text-gray-900">{order.productName}</p>
                {order.isDummy && (
                    <span className="shrink-0 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">
                        ダミー
                    </span>
                )}
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
                <dd className={order.assignee ? 'text-gray-900' : 'text-red-700'}>
                    {order.assignee ?? '未割り当て'}
                </dd>
            </dl>
        </li>
    );
}

export default function Board({ columns, doneTotal, doneLimit }: Props) {
    return (
        <>
            <Head title="進み具合のボード" />
            <div className="min-h-screen bg-gray-50">
                <header className="border-b border-gray-200 bg-white px-4 py-3">
                    <h1 className="text-xl font-semibold text-gray-900">進み具合のボード</h1>
                </header>

                <main className="grid gap-4 p-4 md:grid-cols-3">
                    {columns.map((column) => (
                        <section key={column.status} aria-labelledby={`column-${column.status}`}>
                            <h2
                                id={`column-${column.status}`}
                                className={`mb-3 flex items-baseline justify-between border-b-4 pb-1 ${COLUMN_STYLES[column.status]}`}
                            >
                                <span className="text-lg font-semibold text-gray-900">{column.label}</span>
                                <span className="text-sm text-gray-600">
                                    {column.status === 'done' ? doneTotal : column.orders.length}件
                                </span>
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

                            {column.status === 'done' && doneTotal > doneLimit && (
                                <p className="mt-2 text-sm text-gray-500">
                                    新しいもの{doneLimit}件だけを表示しています
                                </p>
                            )}
                        </section>
                    ))}
                </main>
            </div>
        </>
    );
}
