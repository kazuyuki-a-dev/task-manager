import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../components/AdminLayout';

type CompanyRow = {
    id: number;
    name: string;
    /** この会社の製品の数。0 のときだけ消せる */
    productsCount: number;
    isDummy: boolean;
};

const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2.5';

/** 取引会社（R-14） */
export default function Companies({ companies }: { companies: CompanyRow[] }) {
    const { errors } = usePage<{ errors: { company?: string } }>().props;

    return (
        <AdminLayout title="取引会社">
            <div className="space-y-6">
                <Link href="/admin" className="text-sm font-medium text-blue-700 hover:underline">
                    ← 管理者の画面に戻る
                </Link>

                <AddForm />

                {errors.company && (
                    <p role="alert" className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 font-medium text-red-800">
                        {errors.company}
                    </p>
                )}

                <section>
                    <p className="mb-2 text-sm text-gray-600">全部で{companies.length}社</p>
                    {companies.length === 0 ? (
                        <p className="text-gray-500">まだ登録されていません</p>
                    ) : (
                        <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                            {companies.map((company) => (
                                <Row key={company.id} company={company} />
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}

function AddForm() {
    const form = useForm({ name: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/companies', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-white p-4">
            <h2 className="mb-3 font-semibold text-gray-900">取引会社を足す</h2>
            <div className="flex gap-2">
                <label className="block flex-1">
                    <span className="sr-only">会社名</span>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="会社名" className={inputClass} />
                    {form.errors.name && <span className="mt-1 block text-sm text-red-700">{form.errors.name}</span>}
                </label>
                <button type="submit" disabled={form.processing} className="h-fit shrink-0 rounded-lg bg-blue-600 px-5 py-2.5 font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    足す
                </button>
            </div>
        </form>
    );
}

function Row({ company }: { company: CompanyRow }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ name: company.name });

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(`/admin/companies/${company.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const remove = () => {
        if (!window.confirm(`「${company.name}」を消します。よいですか？`)) return;
        router.delete(`/admin/companies/${company.id}`, { preserveScroll: true });
    };

    if (editing) {
        return (
            <li className="p-3">
                <form onSubmit={save} className="flex flex-wrap gap-2">
                    <label className="block min-w-0 flex-1">
                        <span className="sr-only">会社名</span>
                        <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                        {form.errors.name && <span className="mt-1 block text-sm text-red-700">{form.errors.name}</span>}
                    </label>
                    <button
                        type="button"
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                            setEditing(false);
                        }}
                        className="h-fit rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        やめる
                    </button>
                    <button type="submit" disabled={form.processing} className="h-fit rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        保存
                    </button>
                </form>
            </li>
        );
    }

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-2 p-3">
            <span className="min-w-0 flex-1 font-medium text-gray-900">
                {company.name}
                {company.isDummy && <span className="ml-2 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">ダミー</span>}
            </span>
            {/* 押すと、この会社の製品の一覧が出る */}
            <Link href={`/admin/products?company=${company.id}`} className="text-sm font-medium text-blue-700 hover:underline">
                製品 {company.productsCount}件
            </Link>
            <div className="flex gap-2">
                <button type="button" onClick={() => setEditing(true)} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                    直す
                </button>
                {/* 製品を1つも登録していない会社だけ消せる */}
                {company.productsCount === 0 && (
                    <button type="button" onClick={remove} className="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                        消す
                    </button>
                )}
            </div>
        </li>
    );
}
