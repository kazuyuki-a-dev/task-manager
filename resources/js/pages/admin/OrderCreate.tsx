import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../components/AdminLayout';
import { formatDate } from '../../lib/date';
import type { Member } from '../../types';

type CompanyWithProducts = {
    id: number;
    name: string;
    products: { id: number; code: string | null; name: string }[];
};

type Props = {
    companies: CompanyWithProducts[];
    members: Member[];
};

const inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5';

function FieldError({ message }: { message?: string }) {
    return message ? <span className="mt-1 block text-sm text-red-700">{message}</span> : null;
}

/** 指示の登録（R-03）。取引会社を選ぶと、その会社の製品だけが選べる */
export default function OrderCreate({ companies, members }: Props) {
    const form = useForm({
        company_id: '',
        product_id: '',
        quantity: '',
        material_ready_date: '',
        due_date: '',
        assignee_ids: [] as number[],
    });

    const company = companies.find((c) => String(c.id) === form.data.company_id);
    const candidates = members.filter((member) => !form.data.assignee_ids.includes(member.id));
    const assigned = form.data.assignee_ids.map((id) => members.find((member) => member.id === id)).filter((m): m is Member => m !== undefined);

    // 担当のエラーは「assignee_ids.0」のように番号つきで返ってくるので、1つにまとめて出す
    const errors = form.errors as Record<string, string | undefined>;
    const assigneeError = errors.assignee_ids ?? Object.entries(errors).find(([key]) => key.startsWith('assignee_ids.'))?.[1];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            product_id: data.product_id === '' ? null : Number(data.product_id),
            quantity: data.quantity,
            material_ready_date: data.material_ready_date === '' ? null : data.material_ready_date,
            due_date: data.due_date,
            assignee_ids: data.assignee_ids,
        }));
        form.post('/admin/orders', {
            preserveScroll: true,
            // 続けて同じ会社の指示を入れることが多いので、取引会社はそのまま残す
            onSuccess: () => form.reset('product_id', 'quantity', 'material_ready_date', 'due_date', 'assignee_ids'),
        });
    };

    return (
        <AdminLayout title="指示の登録">
            <div className="space-y-6">
                <Link href="/admin" className="text-sm font-medium text-blue-700 hover:underline">
                    ← 管理者の画面に戻る
                </Link>

                {companies.length === 0 ? (
                    <p className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
                        先に
                        <Link href="/admin/companies" className="mx-1 font-semibold underline">
                            取引会社
                        </Link>
                        と製品を登録してください。
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="mb-1 block font-medium text-gray-700">取引会社</span>
                                <select
                                    value={form.data.company_id}
                                    // 会社を変えたら、選んでいた製品は取り消す（ほかの会社の製品になってしまうため）
                                    onChange={(e) => form.setData((data) => ({ ...data, company_id: e.target.value, product_id: '' }))}
                                    className={inputClass}
                                >
                                    <option value="">選んでください</option>
                                    {companies.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}（製品 {c.products.length}件）
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="block">
                                <span className="mb-1 block font-medium text-gray-700">製品</span>
                                <select
                                    value={form.data.product_id}
                                    onChange={(e) => form.setData('product_id', e.target.value)}
                                    disabled={!company}
                                    className={`${inputClass} disabled:bg-gray-100`}
                                >
                                    <option value="">{company ? (company.products.length > 0 ? '選んでください' : 'この会社には製品がありません') : '先に取引会社を選んでください'}</option>
                                    {company?.products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.code ? `${p.code}　${p.name}` : p.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.product_id} />
                            </label>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <label className="block">
                                <span className="mb-1 block font-medium text-gray-700">数量</span>
                                <input type="number" inputMode="numeric" min={1} value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} className={inputClass} />
                                <FieldError message={form.errors.quantity} />
                            </label>
                            <label className="block">
                                <span className="mb-1 block font-medium text-gray-700">材料集結日（空でもよい）</span>
                                <input type="date" value={form.data.material_ready_date} onChange={(e) => form.setData('material_ready_date', e.target.value)} className={inputClass} />
                                {form.data.material_ready_date && <span className="mt-1 block text-sm text-gray-500">{formatDate(form.data.material_ready_date)}</span>}
                                <FieldError message={form.errors.material_ready_date} />
                            </label>
                            <label className="block">
                                <span className="mb-1 block font-medium text-gray-700">納期</span>
                                <input type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} className={inputClass} />
                                {form.data.due_date && <span className="mt-1 block text-sm text-gray-500">{formatDate(form.data.due_date)}</span>}
                                <FieldError message={form.errors.due_date} />
                            </label>
                        </div>

                        <div>
                            <p className="mb-1 font-medium text-gray-700">担当（空でもよい。あとからボードで足せます）</p>
                            {assigned.length > 0 && (
                                <ul className="mb-2 flex flex-wrap gap-1.5">
                                    {assigned.map((member) => (
                                        <li key={member.id} className="flex items-center rounded-full bg-gray-100 pl-3 text-gray-900">
                                            {member.name}
                                            <button
                                                type="button"
                                                onClick={() => form.setData('assignee_ids', form.data.assignee_ids.filter((id) => id !== member.id))}
                                                aria-label={`${member.name}さんを担当から外す`}
                                                className="ml-1 flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900"
                                            >
                                                ×
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {candidates.length > 0 && (
                                <select
                                    aria-label="担当を足す"
                                    value=""
                                    onChange={(e) => e.target.value !== '' && form.setData('assignee_ids', [...form.data.assignee_ids, Number(e.target.value)])}
                                    className={inputClass}
                                >
                                    <option value="">＋ 担当を足す</option>
                                    {candidates.map((member) => (
                                        <option key={member.id} value={member.id}>
                                            {member.name}
                                        </option>
                                    ))}
                                </select>
                            )}
                            <FieldError message={assigneeError} />
                        </div>

                        <div className="flex justify-end">
                            <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-6 py-3 font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                                登録する
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AdminLayout>
    );
}
