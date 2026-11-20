import { Head, Link, router, usePage } from '@inertiajs/react';
import type { SharedProps } from '../types';

/** 管理者の画面の共通の枠。だれが入っているかと、「管理者の操作を終える」ボタンを出す */
export default function AdminLayout({ title, children }: { title: string; children: React.ReactNode }) {
    const { admin, notice } = usePage<SharedProps>().props;

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-gray-50">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-700 bg-gray-800 px-4 py-3 text-white">
                    <div className="flex items-baseline gap-3">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        {admin && <span className="text-sm text-gray-300">管理者：{admin.name}</span>}
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href="/" className="text-sm font-medium text-gray-200 hover:underline">
                            ボードを見る
                        </Link>
                        <button
                            type="button"
                            onClick={() => router.post('/admin/logout')}
                            className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100"
                        >
                            管理者の操作を終える
                        </button>
                    </div>
                </header>
                <main className="mx-auto max-w-3xl p-4">
                    {notice && (
                        <p role="status" className="mb-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 font-medium text-green-900">
                            {notice}
                        </p>
                    )}
                    {children}
                </main>
            </div>
        </>
    );
}
