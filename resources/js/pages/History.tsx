import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Modal from '../components/Modal';
import { formatDate } from '../lib/date';
import type { PageErrors } from '../types';

type ProductCard = {
    id: number;
    companyName: string;
    code: string | null;
    name: string;
    /** 完了した指示の数 */
    doneCount: number;
    /** 総台数（完了した指示の数量の合計） */
    doneQuantity: number;
    /** 'YYYY-MM-DD'。最後に完了した日（時刻は出さない） */
    lastCompletedOn: string;
    isDummy: boolean;
};

type DoneOrder = {
    id: number;
    /** 'YYYY-MM-DD' */
    dueDate: string;
    /** 注文時の台数 */
    quantity: number;
    /** 担当した人の名前（全員。消した社員も残る） */
    assignees: string[];
};

type Selected = {
    id: number;
    companyName: string;
    code: string | null;
    name: string;
    total: number;
    /** 総台数（完了した指示すべての数量の合計） */
    totalQuantity: number;
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
                                            <span className="block text-sm text-gray-500">最後の完了 {formatDate(product.lastCompletedOn)}</span>
                                        </span>
                                        <span className="flex shrink-0 items-end gap-3 text-right">
                                            <span>
                                                <span className="block text-3xl font-bold leading-none text-gray-900">{product.doneCount}</span>
                                                <span className="text-sm text-gray-500">完了</span>
                                            </span>
                                            {/* 完了件数の右に、総台数 */}
                                            <span>
                                                <span className="block text-lg font-bold leading-none text-gray-700">{product.doneQuantity.toLocaleString()}</span>
                                                <span className="text-sm text-gray-500">総台数</span>
                                            </span>
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

/**
 * 製品のカードを押すと開く小さな画面。その製品の完了した指示を、納期の新しい順に1件1行で出す。
 * たくさんたまるので、出すのは「年・納期・作業者」だけ（D-038）。作業中に戻すボタンも置く
 */
function HistoryModal({ selected, errors, onClose }: { selected: Selected; errors: PageErrors; onClose: () => void }) {
    const [processingId, setProcessingId] = useState<number | null>(null);
    // 「作業者」を押した指示。もう1つ小さな画面を重ねて、作業メンバーを出す
    const [membersOf, setMembersOf] = useState<DoneOrder | null>(null);

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
            subtitle={`${selected.companyName}${selected.code ? `・${selected.code}` : ''}　完了 ${selected.total}件　総台数 ${selected.totalQuantity.toLocaleString()}台`}
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
                        <li key={order.id} className="flex items-center gap-3 py-2 text-sm">
                            <span className="w-14 shrink-0 font-semibold text-gray-900">{order.dueDate.slice(0, 4)}年</span>
                            <span className="w-28 shrink-0 text-gray-900">納期 {formatDate(order.dueDate)}</span>
                            {/* 納期の右に、注文時の台数 */}
                            <span className="min-w-0 flex-1 text-right font-medium text-gray-900">{order.quantity.toLocaleString()}台</span>
                            <button
                                type="button"
                                onClick={() => setMembersOf(order)}
                                aria-label={`納期 ${formatDate(order.dueDate)} の作業者を見る`}
                                className="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            >
                                作業者
                            </button>
                            <button
                                type="button"
                                onClick={() => reopen(order)}
                                disabled={processingId !== null}
                                aria-label={`納期 ${formatDate(order.dueDate)} の指示を作業中に戻す`}
                                className="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                戻す
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {selected.total > selected.limit && (
                <p className="text-sm text-gray-500">新しいもの{selected.limit}件だけを出しています</p>
            )}

            {membersOf && (
                <Modal
                    size="sm"
                    title="作業者"
                    subtitle={`${membersOf.dueDate.slice(0, 4)}年 納期 ${formatDate(membersOf.dueDate)}　${membersOf.quantity.toLocaleString()}台`}
                    onClose={() => setMembersOf(null)}
                >
                    {membersOf.assignees.length === 0 ? (
                        <p className="text-gray-500">記録がありません</p>
                    ) : (
                        <ul className="space-y-1">
                            {membersOf.assignees.map((name, index) => (
                                // 同じ名前の人がいても重ならないように、何番目かも印に入れる
                                <li key={`${index}-${name}`} className="rounded-lg bg-gray-50 px-3 py-2 font-medium text-gray-900">
                                    {name}
                                </li>
                            ))}
                        </ul>
                    )}
                </Modal>
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
