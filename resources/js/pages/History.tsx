import { Head, Link } from '@inertiajs/react';

// 完了の履歴（R-10）。中身は R-10 で作る
export default function History() {
    return (
        <>
            <Head title="完了の履歴" />
            <div className="min-h-screen bg-gray-50">
                <header className="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3">
                    <h1 className="text-xl font-semibold text-gray-900">完了の履歴</h1>
                    <Link href="/" className="text-sm font-medium text-blue-700 hover:underline">
                        ボードに戻る
                    </Link>
                </header>
                <main className="p-4">
                    <p className="text-gray-600">準備中です</p>
                </main>
            </div>
        </>
    );
}
