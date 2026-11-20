import { Link } from '@inertiajs/react';
import AdminLayout from '../../components/AdminLayout';

// 管理者の画面のメニュー。href がないものは、これから作る
const MENU: { label: string; requirement: string; href?: string }[] = [
    { label: '取引会社', requirement: 'R-14', href: '/admin/companies' },
    { label: '製品の一覧', requirement: 'R-12', href: '/admin/products' },
    { label: '指示の登録', requirement: 'R-03（指示を消すのは、ボードの小さな画面から）', href: '/admin/orders/create' },
    { label: '社員', requirement: 'R-07', href: '/admin/members' },
    { label: '会社カレンダー（休みと行事は、日付を押して足す）', requirement: 'R-13', href: '/calendar' },
];

/** 管理者の画面の入口（R-06） */
export default function Home() {
    return (
        <AdminLayout title="管理者の画面">
            <p className="mb-4 text-gray-700">10分操作がないと、管理者の操作は自動で終わります。</p>
            <ul className="grid gap-3 sm:grid-cols-2">
                {MENU.map((item) => (
                    <li key={item.label}>
                        {item.href ? (
                            <Link href={item.href} className="block rounded-lg border border-gray-200 bg-white p-4 hover:border-blue-400 hover:shadow">
                                <p className="font-semibold text-blue-800">{item.label}</p>
                                <p className="mt-1 text-sm text-gray-500">{item.requirement}</p>
                            </Link>
                        ) : (
                            <div className="rounded-lg border border-gray-200 bg-white p-4">
                                <p className="font-semibold text-gray-900">{item.label}</p>
                                <p className="mt-1 text-sm text-gray-500">準備中（{item.requirement}）</p>
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </AdminLayout>
    );
}
