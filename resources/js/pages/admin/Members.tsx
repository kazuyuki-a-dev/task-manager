import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../components/AdminLayout';
import Modal from '../../components/Modal';

type Role = 'worker' | 'admin';

type MemberRow = {
    id: number;
    name: string;
    role: Role;
    isDummy: boolean;
    /** 今入っている管理者（自分自身）かどうか */
    isSelf: boolean;
};

const ROLE_LABELS: Record<Role, string> = { worker: '作業者', admin: '管理者' };
const PASSWORD_MIN = 8;

const inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5';

function FieldError({ message }: { message?: string }) {
    return message ? <span className="mt-1 block text-sm text-red-700">{message}</span> : null;
}

/** 社員の登録（R-07・D-036）。社員はカードで並べ、押すと小さな画面で直す・消す */
export default function Members({ members }: { members: MemberRow[] }) {
    const [openId, setOpenId] = useState<number | null>(null);
    // 消したら見つからないので、小さな画面も閉じる
    const openMember = members.find((member) => member.id === openId);
    const adminCount = members.filter((member) => member.role === 'admin').length;

    return (
        <AdminLayout title="社員">
            <div className="space-y-6">
                <Link href="/admin" className="text-sm font-medium text-blue-700 hover:underline">
                    ← 管理者の画面に戻る
                </Link>

                <AddForm />

                <section>
                    <p className="mb-2 text-sm text-gray-600">
                        全部で{members.length}人（管理者 {adminCount}人）
                    </p>
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {members.map((member) => (
                            <li key={member.id}>
                                <button
                                    type="button"
                                    onClick={() => setOpenId(member.id)}
                                    aria-label={`${member.name}さんを開く`}
                                    className="flex h-full w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm hover:border-blue-400 hover:shadow"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold text-gray-900">{member.name}</span>
                                        {member.isSelf && <span className="text-sm text-gray-500">（自分）</span>}
                                        {member.isDummy && (
                                            <span className="ml-1 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800">ダミー</span>
                                        )}
                                    </span>
                                    <span
                                        className={`shrink-0 rounded-full px-3 py-1 text-sm font-semibold ${
                                            member.role === 'admin' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700'
                                        }`}
                                    >
                                        {ROLE_LABELS[member.role]}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            {openMember && <MemberModal key={openMember.id} member={openMember} onClose={() => setOpenId(null)} />}
        </AdminLayout>
    );
}

function RoleSelect({ value, onChange, disabled }: { value: Role; onChange: (role: Role) => void; disabled?: boolean }) {
    return (
        <select value={value} onChange={(e) => onChange(e.target.value as Role)} disabled={disabled} className={`${inputClass} disabled:bg-gray-100`}>
            <option value="worker">作業者</option>
            <option value="admin">管理者</option>
        </select>
    );
}

/** 社員を足す。管理者にするときだけ、パスワードを決める */
function AddForm() {
    const form = useForm({ name: '', role: 'worker' as Role, password: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, password: data.role === 'admin' ? data.password : null }));
        form.post('/admin/members', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-white p-4">
            <h2 className="mb-3 font-semibold text-gray-900">社員を足す</h2>
            <div className="grid gap-3 sm:grid-cols-[1fr_9rem_1fr_auto] sm:items-start">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">名前</span>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.name} />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">役割</span>
                    <RoleSelect value={form.data.role} onChange={(role) => form.setData('role', role)} />
                    <FieldError message={form.errors.role} />
                </label>
                {form.data.role === 'admin' ? (
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-gray-700">パスワード（{PASSWORD_MIN}文字以上）</span>
                        <input type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className={inputClass} />
                        <FieldError message={form.errors.password} />
                    </label>
                ) : (
                    <p className="text-sm text-gray-500 sm:mt-8">作業者はパスワードを持ちません</p>
                )}
                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2.5 font-bold text-white hover:bg-blue-700 disabled:opacity-50 sm:mt-6">
                    足す
                </button>
            </div>
        </form>
    );
}

/** 社員のカードを押すと開く小さな画面。名前・役割・パスワードを直す、消す */
function MemberModal({ member, onClose }: { member: MemberRow; onClose: () => void }) {
    const { errors } = usePage<{ errors: { member?: string } }>().props;
    const form = useForm({ name: member.name, role: member.role, password: '' });

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, password: data.role === 'admin' && data.password !== '' ? data.password : null }));
        form.patch(`/admin/members/${member.id}`, {
            preserveScroll: true,
            // 保存できたら閉じる。保存できなかったときは、理由が見えるように閉じない
            onSuccess: () => onClose(),
        });
    };

    const remove = () => {
        if (!window.confirm(`${member.name}さんを消します。過去の指示と完了の履歴には名前が残ります。よいですか？`)) return;
        router.delete(`/admin/members/${member.id}`, { preserveScroll: true });
    };

    // 作業者から管理者にするときは、パスワードを決める。もともと管理者なら、空のままで今のパスワードのまま
    const needsPassword = form.data.role === 'admin' && member.role === 'worker';

    return (
        <Modal title={`${member.name}さん`} subtitle={ROLE_LABELS[member.role]} onClose={onClose}>
            {errors.member && (
                <p role="alert" className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 font-medium text-red-800">
                    {errors.member}
                </p>
            )}

            <form onSubmit={save} className="space-y-4">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">名前</span>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.name} />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">役割</span>
                    {/* 自分自身は作業者にできない */}
                    <RoleSelect value={form.data.role} onChange={(role) => form.setData('role', role)} disabled={member.isSelf} />
                    {member.isSelf && <span className="mt-1 block text-sm text-gray-500">自分自身の役割は変えられません</span>}
                    <FieldError message={form.errors.role} />
                </label>
                {form.data.role === 'admin' && (
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-gray-700">
                            {needsPassword ? `パスワード（${PASSWORD_MIN}文字以上）` : `パスワードを変える（${PASSWORD_MIN}文字以上。空なら今のまま）`}
                        </span>
                        <input type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className={inputClass} />
                        <FieldError message={form.errors.password} />
                    </label>
                )}
                <div className="flex items-center justify-end gap-3">
                    <button type="submit" disabled={form.processing || !form.isDirty} className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        保存
                    </button>
                </div>
            </form>

            <section className="flex items-center justify-between gap-3 border-t border-gray-200 pt-4">
                <span className="text-sm text-gray-500">消しても、過去の記録には名前が残ります</span>
                {member.isSelf ? (
                    <span className="text-sm text-gray-500">自分自身は消せません</span>
                ) : (
                    <button type="button" onClick={remove} className="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                        消す
                    </button>
                )}
            </section>
        </Modal>
    );
}
