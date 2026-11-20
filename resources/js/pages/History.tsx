import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Modal from '../components/Modal';
import { formatDate, formatDateTime } from '../lib/date';
import type { PageErrors } from '../types';

type ProductCard = {
    id: number;
    companyName: string;
    code: string | null;
    name: string;
    /** 完了した指示の数 */
    doneCount: number;
    /** 'YYYY-MM-DD HH:mm'。最後に完了した日時 */
    lastCompletedAt: string;
    isDummy: boolean;
};

type DoneOrder = {
    id: number;
    quantity: number;
    dueDate: string;
    startedAt: string | null;
    completedAt: string | null;
    /** 担当した人の名前（全員。消した社員も残る） */
    assignees: string[];
};

type Selected = {
    id: number;
    companyName: string;
    code: string | null;
    name: string;
    total: number;
    limit: number;
    orders: DoneOrder[];
};

type CompanyOption = {
    id: number;
    name: string;
    /** 完了した指示がある製品の数 */
    productsCount: number;
};

type Props = {
    q: string;
    /** 選んでいる取引会社。null なら、すべての会社 */
    companyId: number | null;
    /** 完了した指示がある取引会社だけ */
    companies: CompanyOption[];
    products: { data: ProductCard[]; total: number; currentPage: number; lastPage: number };
    /** 小さな画面で開いている製品。開いていなければ null */
    selected: Selected | null;
    errors: PageErrors;
};

/** 今のアドレスの「探した文字」と「ページ」を残したまま、ほかを変える */
function params(changes: Record<string, string | number | null>): Record<string, string> {
    const current = Object.fromEntries(new URLSearchParams(window.location.search));
    const next: Record<string, string> = { ...current };
    for (const [key, value] of Object.entries(changes)) {
        if (value === null || value === '') delete next[key];
        else next[key] = String(value);
    }
    return next;
}

/**
 * 完了の履歴（R-10）。取引会社を選んでしぼり、完了した指示がある製品をカードで並べる。
 * カードを押すと、その製品の完了した指示が出る
 */
export default function History({ q, companyId, companies, products, selected, errors }: Props) {
    const selectedCompany = companies.find((company) => company.id === companyId);

    const open = (productId: number) => {
        // 開いた製品の分だけを、サーバーから取ってくる（カードは読みなおさない）
        router.get('/history', params({ product: productId }), { only: ['selected'], preserveState: true, preserveScroll: true, replace: true });
    };
    const close = () => {
        router.get('/history', params({ product: null }), { only: ['selected'], preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title="完了の履歴" />
            <div className="min-h-screen bg-gray-50">
                <header className="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3">
                    <h1 className="text-xl font-semibold text-gray-900">完了の履歴</h1>
                    <Link href="/" className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                        ボードに戻る
                    </Link>
                </header>

                <main className="mx-auto max-w-5xl space-y-4 p-4">
                    <Filters q={q} companyId={companyId} companies={companies} />
                    <p className="text-sm text-gray-600">
                        {selectedCompany ? `${selectedCompany.name}の` : 'すべての会社の'}
                        完了した指示がある製品
                        {q && `（「${q}」で探した結果）`}：{products.total}件
                    </p>
                    {products.data.length === 0 ? (
                        <p className="text-gray-500">{q ? '見つかりません' : 'まだ完了した指示はありません'}</p>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {products.data.map((product) => (
                                <li key={product.id}>
                                    <button
                                        type="button"
                                        onClick={() => open(product.id)}
                                        aria-label={`${product.name}の完了した指示を見る`}
                                        className="flex h-full w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm hover:border-blue-400 hover:shadow"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-xs text-gray-500">
                                                {product.companyName}
                                                {product.code && `・${product.code}`}
                                            </span>
                                            <span className="block truncate font-semibold text-gray-900">{product.name}</span>
                                            <span className="block text-sm text-gray-500">最後の完了 {formatDateTime(product.lastCompletedAt)}</span>
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="block text-3xl font-bold leading-none text-gray-900">{product.doneCount}</span>
                                            <span className="text-sm text-gray-500">完了</span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pager currentPage={products.currentPage} lastPage={products.lastPage} />
                </main>
            </div>

            {selected && <HistoryModal key={selected.id} selected={selected} errors={errors} onClose={close} />}
        </>
    );
}

const inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5';

/**
 * 取引会社を選ぶと、その会社の製品だけになる。品名・品番を打つと、さらにしぼられる。
 * 選びなおしたり打ちなおしたりしたら、1ページ目から出しなおす
 */
function Filters({ q, companyId, companies }: { q: string; companyId: number | null; companies: CompanyOption[] }) {
    const [value, setValue] = useState(q);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const visit = (changes: Record<string, string | number | null>) => {
        router.get('/history', params({ page: null, product: null, ...changes }), { preserveState: true, preserveScroll: true, replace: true });
    };

    // 打つたびにサーバーに聞くと多すぎるので、打つ手が 0.3秒止まってから聞く
    useEffect(() => {
        if (value === q) return;
        timer.current = setTimeout(() => visit({ q: value.trim() }), 300);
        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [value, q]);

    return (
        <section className="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2">
            <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">取引会社</span>
                <select value={companyId ?? ''} onChange={(e) => visit({ company: e.target.value === '' ? null : Number(e.target.value) })} className={inputClass}>
                    <option value="">すべての会社</option>
                    {companies.map((company) => (
                        <option key={company.id} value={company.id}>
                            {company.name}（{company.productsCount}製品）
                        </option>
                    ))}
                </select>
            </label>
            <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">品名・品番</span>
                <input
                    type="search"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    placeholder="一部を打つと、合う製品が出ます"
                    className={inputClass}
                />
            </label>
        </section>
    );
}

/** 製品のカードを押すと開く小さな画面。その製品の完了した指示（新しい順）と、作業中に戻すボタン */
function HistoryModal({ selected, errors, onClose }: { selected: Selected; errors: PageErrors; onClose: () => void }) {
    const [processingId, setProcessingId] = useState<number | null>(null);

    // まちがえて完了にしたときに戻す（D-024）。戻すと、完了した日は消え、ボードの「作業中」に出る
    const reopen = (order: DoneOrder) => {
        if (!window.confirm('この指示を「作業中」に戻します。完了した日は消えます。よいですか？')) return;
        router.patch(`/orders/${order.id}/status`, { status: 'in_progress' }, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessingId(order.id),
            onFinish: () => setProcessingId(null),
        });
    };

    return (
        <Modal
            title={selected.name}
            subtitle={`${selected.companyName}${selected.code ? `・${selected.code}` : ''}　完了 ${selected.total}件`}
            onClose={onClose}
        >
            {errors.status && (
                <p role="alert" className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 font-medium text-red-800">
                    {errors.status}
                </p>
            )}
            {selected.orders.length === 0 ? (
                <p className="text-gray-500">完了した指示はありません</p>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {selected.orders.map((order) => (
                        <li key={order.id} className="space-y-1 py-3">
                            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                                <dt className="text-gray-500">完了</dt>
                                <dd className="font-semibold text-gray-900">{order.completedAt ? formatDateTime(order.completedAt) : '—'}</dd>
                                <dt className="text-gray-500">始めた日</dt>
                                <dd className="text-gray-900">{order.startedAt ? formatDateTime(order.startedAt) : '—'}</dd>
                                <dt className="text-gray-500">数量</dt>
                                <dd className="text-gray-900">{order.quantity.toLocaleString()}</dd>
                                <dt className="text-gray-500">納期</dt>
                                <dd className="text-gray-900">{formatDate(order.dueDate)}</dd>
                                <dt className="text-gray-500">担当</dt>
                                <dd className="text-gray-900">{order.assignees.length > 0 ? order.assignees.join('、') : 'なし'}</dd>
                            </dl>
                            <div className="flex justify-end">
                                <button
                                    type="button"
                                    onClick={() => reopen(order)}
                                    disabled={processingId !== null}
                                    className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    作業中に戻す
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {selected.total > selected.limit && (
                <p className="text-sm text-gray-500">新しいもの{selected.limit}件だけを出しています</p>
            )}
        </Modal>
    );
}

/** 30件ずつのページの切りかえ */
function Pager({ currentPage, lastPage }: { currentPage: number; lastPage: number }) {
    if (lastPage <= 1) return null;
    const go = (page: number) => router.get('/history', params({ page, product: null }), { preserveScroll: false });
    const buttonClass = 'rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50';

    return (
        <nav className="flex items-center justify-center gap-3" aria-label="ページ">
            {currentPage > 1 && (
                <button type="button" onClick={() => go(currentPage - 1)} className={buttonClass}>
                    前へ
                </button>
            )}
            <span className="text-sm text-gray-600">
                {currentPage} / {lastPage}
            </span>
            {currentPage < lastPage && (
                <button type="button" onClick={() => go(currentPage + 1)} className={buttonClass}>
                    次へ
                </button>
            )}
        </nav>
    );
}
