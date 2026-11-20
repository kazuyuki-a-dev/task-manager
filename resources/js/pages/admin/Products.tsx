import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../components/AdminLayout';

type ProductRow = {
    id: number;
    code: string | null;
    name: string;
    /** この製品を使った指示の数。0 のときだけ消せる */
    ordersCount: number;
    isDummy: boolean;
};

type Props = {
    q: string;
    products: {
        data: ProductRow[];
        total: number;
        currentPage: number;
        lastPage: number;
    };
};

const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2.5';

/** 製品の一覧と検索（R-12） */
export default function Products({ q, products }: Props) {
    const { errors } = usePage<{ errors: { product?: string } }>().props;

    return (
        <AdminLayout title="製品の一覧">
            <div className="space-y-6">
                <Link href="/admin" className="text-sm font-medium text-blue-700 hover:underline">
                    ← 管理者の画面に戻る
                </Link>

                <AddForm />

                <section className="space-y-3">
                    <SearchForm q={q} />
                    {errors.product && (
                        <p role="alert" className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 font-medium text-red-800">
                            {errors.product}
                        </p>
                    )}
                    <p className="text-sm text-gray-600">
                        {q ? `「${q}」で探した結果：` : '全部で'}
                        {products.total}件
                    </p>
                    {products.data.length === 0 ? (
                        <p className="text-gray-500">見つかりません</p>
                    ) : (
                        <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                            {products.data.map((product) => (
                                <Row key={product.id} product={product} />
                            ))}
                        </ul>
                    )}
                    <Pager q={q} currentPage={products.currentPage} lastPage={products.lastPage} />
                </section>
            </div>
        </AdminLayout>
    );
}

/** 製品を足す */
function AddForm() {
    const form = useForm({ code: '', name: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/products', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-white p-4">
            <h2 className="mb-3 font-semibold text-gray-900">製品を足す</h2>
            <div className="grid gap-3 sm:grid-cols-[10rem_1fr_auto] sm:items-start">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">品番（空でもよい）</span>
                    <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} className={inputClass} />
                    {form.errors.code && <span className="mt-1 block text-sm text-red-700">{form.errors.code}</span>}
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">品名</span>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                    {form.errors.name && <span className="mt-1 block text-sm text-red-700">{form.errors.name}</span>}
                </label>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded-lg bg-blue-600 px-5 py-2.5 font-bold text-white hover:bg-blue-700 disabled:opacity-50 sm:mt-6"
                >
                    足す
                </button>
            </div>
        </form>
    );
}

/** 品番か品名の一部で探す。探すのはサーバーでする（製品が増えても重くならないように） */
function SearchForm({ q }: { q: string }) {
    const [value, setValue] = useState(q);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/admin/products', value.trim() === '' ? {} : { q: value.trim() }, { preserveState: true });
    };

    return (
        <form onSubmit={submit} className="flex gap-2">
            <input
                type="search"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                placeholder="品番・品名の一部で探す"
                aria-label="製品を探す"
                className={inputClass}
            />
            <button type="submit" className="shrink-0 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-semibold text-gray-800 hover:bg-gray-50">
                探す
            </button>
        </form>
    );
}

/** 一覧の1行。「直す」で、その場で直せる */
function Row({ product }: { product: ProductRow }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ code: product.code ?? '', name: product.name });

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(`/admin/products/${product.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const remove = () => {
        if (!window.confirm(`「${product.name}」を消します。よいですか？`)) return;
        router.delete(`/admin/products/${product.id}`, { preserveScroll: true });
    };

    if (editing) {
        return (
            <li className="p-3">
                <form onSubmit={save} className="grid gap-2 sm:grid-cols-[10rem_1fr_auto] sm:items-start">
                    <label className="block">
                        <span className="sr-only">品番</span>
                        <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="品番（空でもよい）" className={inputClass} />
                        {form.errors.code && <span className="mt-1 block text-sm text-red-700">{form.errors.code}</span>}
                    </label>
                    <label className="block">
                        <span className="sr-only">品名</span>
                        <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                        {form.errors.name && <span className="mt-1 block text-sm text-red-700">{form.errors.name}</span>}
                    </label>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                form.reset();
                                form.clearErrors();
                                setEditing(false);
                            }}
                            className="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            やめる
                        </button>
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            保存
                        </button>
                    </div>
                </form>
            </li>
        );
    }

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-2 p-3">
            <span className="w-32 shrink-0 text-sm text-gray-500">{product.code}</span>
            <span className="min-w-0 flex-1 font-medium text-gray-900">
                {product.name}
                {product.isDummy && (
                    <span className="ml-2 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">ダミー</span>
                )}
            </span>
            <span className="text-sm text-gray-600">指示 {product.ordersCount}件</span>
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={() => setEditing(true)}
                    className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                >
                    直す
                </button>
                {/* 指示に使ったことがない製品だけ消せる */}
                {product.ordersCount === 0 && (
                    <button
                        type="button"
                        onClick={remove}
                        className="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                    >
                        消す
                    </button>
                )}
            </div>
        </li>
    );
}

/** 50件ずつのページの切りかえ */
function Pager({ q, currentPage, lastPage }: { q: string; currentPage: number; lastPage: number }) {
    if (lastPage <= 1) return null;
    const href = (page: number) => `/admin/products?${new URLSearchParams({ ...(q ? { q } : {}), page: String(page) })}`;
    const linkClass = 'rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50';

    return (
        <nav className="flex items-center justify-center gap-3" aria-label="ページ">
            {currentPage > 1 && <Link href={href(currentPage - 1)} className={linkClass}>前へ</Link>}
            <span className="text-sm text-gray-600">
                {currentPage} / {lastPage}
            </span>
            {currentPage < lastPage && <Link href={href(currentPage + 1)} className={linkClass}>次へ</Link>}
        </nav>
    );
}
