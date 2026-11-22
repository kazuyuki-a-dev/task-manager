import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { Member, SharedProps } from '../../types';

type Props = {
    /** 選べる管理者（使える人だけ） */
    admins: Member[];
    /** お試し用のデモのときだけ、入り方（D-048）。デモでなければ null */
    demoLogin: { name: string; password: string } | null;
};

/** 管理者のパスワードを入れる画面（R-06） */
export default function Login({ admins, demoLogin }: Props) {
    const { notice } = usePage<SharedProps>().props;
    const form = useForm({
        // 管理者が1人だけなら、はじめから選んでおく
        user_id: admins.length === 1 ? String(admins[0].id) : '',
        password: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/login', {
            // まちがえたときは、パスワードの欄を空にする
            onError: () => form.reset('password'),
        });
    };

    return (
        <>
            <Head title="管理者のパスワード" />
            <div className="min-h-screen bg-gray-50">
                <header className="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3">
                    <h1 className="text-xl font-semibold text-gray-900">管理者</h1>
                    <Link href="/" className="text-sm font-medium text-blue-700 hover:underline">
                        ボードに戻る
                    </Link>
                </header>

                <main className="mx-auto max-w-md p-4">
                    {notice && (
                        <p role="status" className="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
                            {notice}
                        </p>
                    )}

                    {demoLogin && (
                        <section className="mb-4 rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-blue-900">
                            <p className="font-bold">お試し用のデモの入り方</p>
                            <p className="mt-1">
                                名前：{demoLogin.name}／パスワード：<span className="font-mono">{demoLogin.password}</span>
                            </p>
                        </section>
                    )}

                    <form onSubmit={submit} className="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
                        <p className="text-gray-700">管理者の操作をするには、パスワードを入れてください。</p>

                        <label className="block">
                            <span className="mb-1 block font-medium text-gray-700">名前</span>
                            <select
                                value={form.data.user_id}
                                onChange={(event) => form.setData('user_id', event.target.value)}
                                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5"
                            >
                                <option value="">選んでください</option>
                                {admins.map((admin) => (
                                    <option key={admin.id} value={admin.id}>
                                        {admin.name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.user_id && <span className="mt-1 block text-sm text-red-700">{form.errors.user_id}</span>}
                        </label>

                        <label className="block">
                            <span className="mb-1 block font-medium text-gray-700">パスワード</span>
                            <input
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5"
                            />
                            {form.errors.password && <span className="mt-1 block text-sm text-red-700">{form.errors.password}</span>}
                        </label>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="w-full rounded-lg bg-gray-800 px-4 py-3 font-bold text-white hover:bg-gray-900 disabled:opacity-50"
                        >
                            管理者の画面へ
                        </button>
                    </form>
                </main>
            </div>
        </>
    );
}
