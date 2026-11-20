import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AdminLayout from '../../components/AdminLayout';
import type { Company } from '../../types';

type ProductRow = {
    id: number;
    companyId: number;
    companyName: string;
    code: string | null;
    name: string;
    /** この製品を使った指示の数。0 のときだけ消せる */
    ordersCount: number;
    isDummy: boolean;
};

type Props = {
    q: string;
    /** 選んでいる取引会社。null なら、すべての会社 */
    companyId: number | null;
    companies: Company[];
    products: {
        data: ProductRow[];
        total: number;
        currentPage: number;
        lastPage: number;
    };
};

const inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5';

/** 一覧を出しなおす。取引会社と、打った文字で、サーバーがしぼりこむ */
function visit(companyId: number | null, q: string) {
    const params: Record<string, string> = {};
    if (companyId !== null) params.company = String(companyId);
    if (q.trim() !== '') params.q = q.trim();
    router.get('/admin/products', params, { preserveState: true, preserveScroll: true, replace: true });
}

/** 製品の一覧と検索（R-12）。取引会社を選ぶと、その会社の製品が下に出る（D-033） */
export default function Products({ q, companyId, companies, products }: Props) {
    const { errors } = usePage<{ errors: { product?: string } }>().props;
    const selectedCompany = companies.find((company) => company.id === companyId);

    return (
        <AdminLayout title="製品の一覧">
            <div className="space-y-6">
                <Link href="/admin" className="text-sm font-medium text-blue-700 hover:underline">
                    ← 管理者の画面に戻る
                </Link>

                {companies.length === 0 ? (
                    <p className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
                        製品は、どこかの取引会社のものとして登録します。先に
                        <Link href="/admin/companies" className="mx-1 font-semibold underline">
                            取引会社
                        </Link>
                        を登録してください。
                    </p>
                ) : (
                    <>
                        <Filters q={q} companyId={companyId} companies={companies} />
                        <AddForm companies={companies} companyId={companyId} />
                    </>
                )}

                <section className="space-y-3">
                    {errors.product && (
                        <p role="alert" className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 font-medium text-red-800">
                            {errors.product}
                        </p>
                    )}
                    <p className="text-sm text-gray-600">
                        {selectedCompany ? `${selectedCompany.name}の製品` : 'すべての会社の製品'}
                        {q && `（「${q}」で探した結果）`}：{products.total}件
                    </p>
                    {products.data.length === 0 ? (
                        <p className="text-gray-500">見つかりません</p>
                    ) : (
                        <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                            {products.data.map((product) => (
                                <Row key={product.id} product={product} companies={companies} showCompany={companyId === null} />
                            ))}
                        </ul>
                    )}
                    <Pager q={q} companyId={companyId} currentPage={products.currentPage} lastPage={products.lastPage} />
                </section>
            </div>
        </AdminLayout>
    );
}

/** 取引会社を選ぶ欄と、品名・品番を打つ欄。打つたびに、下の一覧がしぼられる */
function Filters({ q, companyId, companies }: { q: string; companyId: number | null; companies: Company[] }) {
    const [value, setValue] = useState(q);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // 打つたびにサーバーに聞くと多すぎるので、打つ手が 0.3秒止まってから聞く
    useEffect(() => {
        if (value === q) return;
        timer.current = setTimeout(() => visit(companyId, value), 300);
        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [value, q, companyId]);

    return (
        <section className="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2">
            <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">取引会社</span>
                <select
                    value={companyId ?? ''}
                    onChange={(e) => visit(e.target.value === '' ? null : Number(e.target.value), value)}
                    className={inputClass}
                >
                    <option value="">すべての会社</option>
                    {companies.map((company) => (
                        <option key={company.id} value={company.id}>
                            {company.name}
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

function CompanySelect({ companies, value, onChange, error }: { companies: Company[]; value: string; onChange: (value: string) => void; error?: string }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700">取引会社</span>
            <select value={value} onChange={(e) => onChange(e.target.value)} className={inputClass}>
                <option value="">選んでください</option>
                {companies.map((company) => (
                    <option key={company.id} value={company.id}>
                        {company.name}
                    </option>
                ))}
            </select>
            {error && <span className="mt-1 block text-sm text-red-700">{error}</span>}
        </label>
    );
}

/** 製品を足す。上で取引会社を選んでいれば、その会社をはじめから選んでおく */
function AddForm({ companies, companyId }: { companies: Company[]; companyId: number | null }) {
    const form = useForm({ company_id: companyId ? String(companyId) : '', code: '', name: '' });

    // 上の取引会社を選びなおしたら、足す先もそれに合わせる
    useEffect(() => {
        form.setData('company_id', companyId ? String(companyId) : '');
    }, [companyId]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/products', {
            preserveScroll: true,
            onSuccess: () => form.reset('code', 'name'),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-white p-4">
            <h2 className="mb-3 font-semibold text-gray-900">製品を足す</h2>
            <div className="grid gap-3 sm:grid-cols-[1fr_9rem_1fr_auto] sm:items-start">
                <CompanySelect companies={companies} value={form.data.company_id} onChange={(v) => form.setData('company_id', v)} error={form.errors.company_id} />
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
                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2.5 font-bold text-white hover:bg-blue-700 disabled:opacity-50 sm:mt-6">
                    足す
                </button>
            </div>
        </form>
    );
}

/** 一覧の1行。「直す」で、その場で直せる */
function Row({ product, companies, showCompany }: { product: ProductRow; companies: Company[]; showCompany: boolean }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ company_id: String(product.companyId), code: product.code ?? '', name: product.name });

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(`/admin/products/${product.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const remove = () => {
        if (!window.confirm(`「${product.name}」を消します。よいですか？`)) return;
        router.delete(`/admin/products/${product.id}`, { preserveScroll: true });
    };

    if (editing) {
        return (
            <li className="p-3">
                <form onSubmit={save} className="grid gap-2 sm:grid-cols-[1fr_9rem_1fr] sm:items-start">
                    <CompanySelect companies={companies} value={form.data.company_id} onChange={(v) => form.setData('company_id', v)} error={form.errors.company_id} />
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-gray-700">品番</span>
                        <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="空でもよい" className={inputClass} />
                        {form.errors.code && <span className="mt-1 block text-sm text-red-700">{form.errors.code}</span>}
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-gray-700">品名</span>
                        <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                        {form.errors.name && <span className="mt-1 block text-sm text-red-700">{form.errors.name}</span>}
                    </label>
                    <div className="flex justify-end gap-2 sm:col-span-3">
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
            <span className="w-28 shrink-0 text-sm text-gray-500">{product.code}</span>
            <span className="min-w-0 flex-1 font-medium text-gray-900">
                {product.name}
                {product.isDummy && <span className="ml-2 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">ダミー</span>}
                {/* すべての会社を出しているときだけ、どの会社の製品かを出す */}
                {showCompany && <span className="block text-sm font-normal text-gray-500">{product.companyName}</span>}
            </span>
            <span className="text-sm text-gray-600">指示 {product.ordersCount}件</span>
            <div className="flex gap-2">
                <button type="button" onClick={() => setEditing(true)} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                    直す
                </button>
                {/* 指示に使ったことがない製品だけ消せる */}
                {product.ordersCount === 0 && (
                    <button type="button" onClick={remove} className="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                        消す
                    </button>
                )}
            </div>
        </li>
    );
}

/** 50件ずつのページの切りかえ */
function Pager({ q, companyId, currentPage, lastPage }: { q: string; companyId: number | null; currentPage: number; lastPage: number }) {
    if (lastPage <= 1) return null;
    const href = (page: number) => {
        const params = new URLSearchParams({ page: String(page) });
        if (companyId !== null) params.set('company', String(companyId));
        if (q) params.set('q', q);
        return `/admin/products?${params}`;
    };
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
