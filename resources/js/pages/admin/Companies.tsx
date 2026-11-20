import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AdminLayout from '../../components/AdminLayout';

type CompanyRow = {
    id: number;
    name: string;
    /** この会社の製品の数。0 のときだけ消せる */
    productsCount: number;
    isDummy: boolean;
};

const inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5';

function FieldError({ message }: { message?: string }) {
    return message ? <span className="mt-1 block text-sm text-red-700">{message}</span> : null;
}

/** 取引会社（R-14・D-034）。会社はカードで並べ、押すと小さな画面で直す・製品を足す・消す */
export default function Companies({ companies }: { companies: CompanyRow[] }) {
    const [openId, setOpenId] = useState<number | null>(null);
    // 開いている会社は、毎回サーバーから届いた新しい内容で出す。消したら見つからないので閉じる
    const openCompany = companies.find((company) => company.id === openId);

    return (
        <AdminLayout title="取引会社">
            <div className="space-y-6">
                <Link href="/admin" className="text-sm font-medium text-blue-700 hover:underline">
                    ← 管理者の画面に戻る
                </Link>

                <AddForm />

                <section>
                    <p className="mb-2 text-sm text-gray-600">全部で{companies.length}社</p>
                    {companies.length === 0 ? (
                        <p className="text-gray-500">まだ登録されていません</p>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {companies.map((company) => (
                                <li key={company.id}>
                                    <button
                                        type="button"
                                        onClick={() => setOpenId(company.id)}
                                        aria-label={`${company.name}を開く`}
                                        className="flex h-full w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm hover:border-blue-400 hover:shadow"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-semibold text-gray-900">{company.name}</span>
                                            {company.isDummy && (
                                                <span className="mt-1 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">
                                                    ダミー
                                                </span>
                                            )}
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="block text-3xl font-bold leading-none text-gray-900">{company.productsCount}</span>
                                            <span className="text-sm text-gray-500">製品</span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            {openCompany && <CompanyModal key={openCompany.id} company={openCompany} onClose={() => setOpenId(null)} />}
        </AdminLayout>
    );
}

/** 会社を足す。品名もいっしょに入れれば、その会社の製品を1件足す（空なら製品0件） */
function AddForm() {
    const form = useForm({ name: '', product_code: '', product_name: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/companies', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-white p-4">
            <h2 className="mb-3 font-semibold text-gray-900">取引会社を足す</h2>
            <div className="grid gap-3 sm:grid-cols-[1fr_9rem_1fr_auto] sm:items-start">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">会社名</span>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.name} />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">品番（空でもよい）</span>
                    <input value={form.data.product_code} onChange={(e) => form.setData('product_code', e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.product_code} />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">品名（空でもよい）</span>
                    <input value={form.data.product_name} onChange={(e) => form.setData('product_name', e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.product_name} />
                </label>
                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2.5 font-bold text-white hover:bg-blue-700 disabled:opacity-50 sm:mt-6">
                    足す
                </button>
            </div>
            <p className="mt-2 text-sm text-gray-500">品番・品名が空なら、製品0件の会社として登録します。登録してある会社に製品を足すときは、下のカードを押してください。</p>
        </form>
    );
}

/** 会社のカードを押すと開く小さな画面。会社名を直す・製品を足す・消す */
function CompanyModal({ company, onClose }: { company: CompanyRow; onClose: () => void }) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const { errors } = usePage<{ errors: { company?: string } }>().props;

    useEffect(() => {
        const dialog = dialogRef.current;
        dialog?.showModal();
        return () => dialog?.close();
    }, []);

    const remove = () => {
        if (!window.confirm(`「${company.name}」を消します。よいですか？`)) return;
        router.delete(`/admin/companies/${company.id}`, { preserveScroll: true });
    };

    return (
        <dialog
            ref={dialogRef}
            onClose={onClose}
            onClick={(event) => event.target === dialogRef.current && onClose()}
            aria-labelledby="company-modal-title"
            className="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl p-0 shadow-xl backdrop:bg-black/40"
        >
            <div className="max-h-[85vh] overflow-y-auto">
                <header className="sticky top-0 flex items-start justify-between gap-2 border-b border-gray-200 bg-white px-4 py-3">
                    <div>
                        <h2 id="company-modal-title" className="text-lg font-semibold text-gray-900">
                            {company.name}
                        </h2>
                        <p className="text-sm text-gray-500">製品 {company.productsCount}件</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="閉じる"
                        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-2xl text-gray-500 hover:bg-gray-100"
                    >
                        ×
                    </button>
                </header>

                <div className="space-y-6 px-4 py-4">
                    {errors.company && (
                        <p role="alert" className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 font-medium text-red-800">
                            {errors.company}
                        </p>
                    )}
                    <RenameForm company={company} />
                    <AddProductForm company={company} />
                    <section className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
                        <Link href={`/admin/products?company=${company.id}`} className="text-sm font-medium text-blue-700 hover:underline">
                            この会社の製品の一覧を見る（直す・消す）
                        </Link>
                        {/* 製品を1つも登録していない会社だけ消せる（D-033） */}
                        {company.productsCount === 0 ? (
                            <button type="button" onClick={remove} className="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                この会社を消す
                            </button>
                        ) : (
                            <span className="text-sm text-gray-500">製品がある会社は消せません</span>
                        )}
                    </section>
                </div>
            </div>
        </dialog>
    );
}

function SectionTitle({ children }: { children: React.ReactNode }) {
    return <h3 className="mb-2 text-sm font-semibold text-gray-500">{children}</h3>;
}

function RenameForm({ company }: { company: CompanyRow }) {
    const form = useForm({ name: company.name });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(`/admin/companies/${company.id}`, { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    return (
        <form onSubmit={submit}>
            <SectionTitle>会社名</SectionTitle>
            <div className="flex gap-2">
                <label className="block flex-1">
                    <span className="sr-only">会社名</span>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.name} />
                </label>
                <button type="submit" disabled={form.processing || !form.isDirty} className="h-fit shrink-0 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    直す
                </button>
            </div>
        </form>
    );
}

/** この会社に製品を足す */
function AddProductForm({ company }: { company: CompanyRow }) {
    const form = useForm({ company_id: company.id, code: '', name: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/products', { preserveScroll: true, onSuccess: () => form.reset('code', 'name') });
    };

    return (
        <form onSubmit={submit}>
            <SectionTitle>製品を足す</SectionTitle>
            <div className="grid gap-2 sm:grid-cols-[8rem_1fr_auto] sm:items-start">
                <label className="block">
                    <span className="sr-only">品番</span>
                    <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="品番（空でもよい）" className={inputClass} />
                    <FieldError message={form.errors.code} />
                </label>
                <label className="block">
                    <span className="sr-only">品名</span>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="品名" className={inputClass} />
                    <FieldError message={form.errors.name} />
                </label>
                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    足す
                </button>
            </div>
            {form.recentlySuccessful && <p className="mt-1 text-sm font-medium text-green-700">足しました</p>}
        </form>
    );
}
